import { useEffect, useId, useRef, useState, type DragEvent } from 'react';
import { getPath } from '../expr';
import { lang, t } from '../i18n';
import { useStore } from '../store';
import { upload, type UploadResult } from '../transport';
import { Avatar, buttonClass, cx, Icon } from '../ui';
import type { FieldNode } from '../types';
import { FieldFrame, useField } from './fields';

type UploadNode = FieldNode & {
  /** Upload::avatar(): a round photo with buttons instead of a drop zone. */
  avatar?: { initials: string; hue: number };
  multiple?: boolean;
  accept?: string;
  maxSize: number;
  maxFiles?: number;
  files?: UploadResult[];
};

interface Pending {
  key: number;
  file: File;
  progress: number;
  error?: string;
  /** The error is about the file itself, so trying again will not help. */
  final?: boolean;
  abort?: () => void;
}

const UNITS = { ru: ['Б', 'КБ', 'МБ', 'ГБ'], en: ['B', 'KB', 'MB', 'GB'] };

export function formatSize(bytes: number): string {
  let value = bytes;
  let unit = 0;
  while (value >= 1024 && unit < 3) {
    value /= 1024;
    unit++;
  }
  return `${value.toLocaleString(lang() === 'ru' ? 'ru-RU' : 'en-US', { maximumFractionDigits: unit < 2 ? 0 : 1 })} ${UNITS[lang()][unit]}`;
}

/** Mirrors Upload::matchesType() on the server; the server checks again. */
function accepts(accept: string | undefined, file: File): boolean {
  if (!accept) return true;
  return accept.split(',').some((raw) => {
    const type = raw.trim().toLowerCase();
    if (type.startsWith('.')) return file.name.toLowerCase().endsWith(type);
    if (type.endsWith('/*')) return file.type.startsWith(type.slice(0, -1)) && file.type !== 'image/svg+xml';
    return file.type === type;
  });
}

/** "image/*" → "Images", ".pdf" → "PDF", "application/zip" → "ZIP". */
function typeLabel(raw: string): string {
  const type = raw.trim().toLowerCase();
  if (type.endsWith('/*')) {
    const group = type.slice(0, -2);
    return group === 'image' || group === 'video' || group === 'audio' ? t(group) : type;
  }
  return (type.startsWith('.') ? type.slice(1) : (type.split('/').pop() ?? type)).toUpperCase();
}

const extension = (name: string) => (name.includes('.') ? name.split('.').pop()!.slice(0, 4).toUpperCase() : 'FILE');

function FileBadge({ name, mime, url }: { name: string; mime?: string; url?: string | null }) {
  if (url && mime?.startsWith('image/')) {
    return <img src={url} alt="" className="size-10 shrink-0 rounded-lg border border-line object-cover" />;
  }
  return (
    <span aria-hidden="true" className="grid size-10 shrink-0 place-items-center rounded-lg bg-surface-2 font-mono text-[10.5px] font-semibold text-fg-2">
      {extension(name)}
    </span>
  );
}

export function UploadField({ node }: { node: UploadNode }) {
  const id = useId();
  const store = useStore();
  const field = useField(node);
  const input = useRef<HTMLInputElement>(null);
  const counter = useRef(0);
  const [known, setKnown] = useState<Record<number, UploadResult>>({});
  const [pending, setPending] = useState<Pending[]>([]);
  const [over, setOver] = useState(false);

  // Files described by the server (on load and after a refresh) join the ones uploaded here.
  useEffect(() => setKnown((current) => ({ ...current, ...Object.fromEntries((node.files ?? []).map((file) => [file.id, file])) })), [node.files]);

  const currentIds = (): number[] => {
    const value = getPath(store.get().page.state, node.path);
    return (Array.isArray(value) ? value : value === null || value === undefined ? [] : [value]).map(Number);
  };
  const ids = (Array.isArray(field.value) ? field.value : field.value === null || field.value === undefined ? [] : [field.value]).map(Number);
  const room = node.multiple ? (node.maxFiles ?? Infinity) - ids.length - pending.filter((item) => !item.error).length : ids.length === 0 && pending.length === 0 ? 1 : 0;

  const patch = (key: number, change: Partial<Pending>) => setPending((current) => current.map((item) => (item.key === key ? { ...item, ...change } : item)));

  const send = (item: Pending) => {
    const request = upload(store.get().page, node.path, item.file, (progress) => patch(item.key, { progress }));
    patch(item.key, { abort: request.abort, error: undefined, progress: 0 });

    request.promise
      .then(({ status, data }) => {
        if (status === 200) {
          setKnown((current) => ({ ...current, [data.id]: data }));
          setPending((current) => current.filter((entry) => entry.key !== item.key));
          store.setValue(node.path, node.multiple ? [...currentIds(), data.id] : data.id);
        } else {
          patch(item.key, { error: data.errors?.[node.path]?.[0] ?? t('uploadFailed'), final: status === 422 });
        }
      })
      .catch((error: unknown) => {
        if (error instanceof DOMException && error.name === 'AbortError') setPending((current) => current.filter((entry) => entry.key !== item.key));
        else patch(item.key, { error: t('uploadFailed') });
      });
  };

  const start = (files: FileList | null) => {
    if (!files) return;
    // Read the room from the store: a replaced avatar was removed a moment ago, in the same event.
    const free = node.multiple ? (node.maxFiles ?? Infinity) - currentIds().length - pending.filter((item) => !item.error).length : currentIds().length === 0 ? 1 : 0;
    const chosen = [...files].slice(0, Math.max(0, free));
    const items = chosen.map<Pending>((file) => ({
      key: ++counter.current,
      file,
      progress: 0,
      ...(file.size > node.maxSize
        ? { error: t('tooLarge', { size: formatSize(file.size), max: formatSize(node.maxSize) }), final: true }
        : !accepts(node.accept, file)
          ? { error: t('wrongType'), final: true }
          : {}),
    }));
    setPending((current) => [...current, ...items]);
    items.filter((item) => !item.error).forEach(send);
    if (input.current) input.current.value = '';
  };

  const remove = (fileId: number) => store.setValue(node.path, node.multiple ? currentIds().filter((entry) => entry !== fileId) : null);

  const onDrop = (event: DragEvent) => {
    event.preventDefault();
    setOver(false);
    if (!field.disabled) start(event.dataTransfer.files);
  };

  const hint = [node.accept?.split(',').map(typeLabel).join(', '), t('upTo', { size: formatSize(node.maxSize) })].filter(Boolean).join(' · ');

  if (node.avatar) {
    const file = ids[0] !== undefined ? known[ids[0]] : undefined;
    const item = pending[0];
    return (
      <FieldFrame node={node} inputId={id} required={field.required} error={field.error}>
        <div className="flex flex-wrap items-center gap-4">
          <Avatar initials={node.avatar.initials} hue={node.avatar.hue} image={file?.url} size="lg" />
          <div className="flex flex-col gap-1.5">
            {!field.disabled && (
              <div className="flex gap-2">
                <label htmlFor={id} className={cx(buttonClass('secondary', 'sm'), item && !item.error && 'pointer-events-none opacity-50')}>
                  {item && !item.error ? `${Math.round(item.progress * 100)}%` : t('uploadPhoto')}
                </label>
                {ids.length > 0 && (
                  <button type="button" className={buttonClass('ghost', 'sm')} onClick={() => remove(ids[0]!)}>
                    {t('remove')}
                  </button>
                )}
              </div>
            )}
            {item?.error && <span className="text-[12.5px] text-danger-fg">{item.error}</span>}
          </div>
          <input
            ref={input}
            id={id}
            type="file"
            className="sr-only"
            accept={node.accept}
            onChange={(event) => {
              setPending([]);
              if (ids[0] !== undefined) remove(ids[0]);
              start(event.target.files);
            }}
          />
        </div>
      </FieldFrame>
    );
  }

  return (
    <FieldFrame node={node} inputId={id} required={field.required} error={field.error}>
      {room > 0 && !field.disabled && (
        <div
          onDragOver={(event) => {
            event.preventDefault();
            setOver(true);
          }}
          onDragLeave={() => setOver(false)}
          onDrop={onDrop}
          className={cx(
            'flex flex-col items-center gap-1.5 rounded-xl border border-dashed px-4 py-6 text-center transition-colors',
            over ? 'border-accent bg-accent-soft' : 'border-line-strong bg-surface-2/40',
            field.error && 'border-danger',
          )}
        >
          <span aria-hidden="true" className="mb-1 grid size-9 place-items-center rounded-full bg-surface text-fg-3 shadow-sm">
            <Icon name="upload" />
          </span>
          <span className="text-[14px] text-fg-2">
            <label htmlFor={id} className="cursor-pointer font-medium text-accent-fg underline-offset-4 hover:underline">
              {t(node.multiple ? 'chooseFiles' : 'chooseFile')}
            </label>{' '}
            {t('orDrop')}
          </span>
          <span className="text-[12.5px] text-fg-3">{hint}</span>
          <input ref={input} id={id} type="file" className="sr-only" multiple={node.multiple} accept={node.accept} onChange={(event) => start(event.target.files)} />
        </div>
      )}

      {(ids.length > 0 || pending.length > 0) && (
        <ul className="flex flex-col divide-y divide-line rounded-xl border border-line">
          {ids.map((fileId) => {
            const file = known[fileId];
            return (
              <li key={fileId} className="flex items-center gap-3 px-3 py-2.5">
                <FileBadge name={file?.name ?? String(fileId)} mime={file?.mime} url={file?.url} />
                <div className="flex min-w-0 flex-1 flex-col">
                  {file?.url ? (
                    <a href={file.url} target="_blank" rel="noreferrer" className="truncate text-[14px] font-medium text-fg hover:underline">
                      {file.name}
                    </a>
                  ) : (
                    <span className="truncate text-[14px] font-medium">{file?.name ?? `#${fileId}`}</span>
                  )}
                  {file && <span className="text-[12.5px] text-fg-3">{formatSize(file.size)}</span>}
                </div>
                {!field.disabled && (
                  <button type="button" aria-label={t('removeFile', { name: file?.name ?? String(fileId) })} onClick={() => remove(fileId)} className="grid size-7 place-items-center rounded-md text-fg-3 hover:bg-surface-2 hover:text-fg">
                    <Icon name="trash" />
                  </button>
                )}
              </li>
            );
          })}
          {pending.map((item) => (
            <li key={item.key} className="flex items-center gap-3 px-3 py-2.5">
              <FileBadge name={item.file.name} />
              <div className="flex min-w-0 flex-1 flex-col gap-1">
                <span className="flex items-center gap-2 truncate text-[14px] font-medium">
                  {item.file.name}
                  {!item.error && <span className="text-[12.5px] font-normal text-fg-3 tabular-nums">{Math.round(item.progress * 100)}%</span>}
                </span>
                {item.error ? (
                  <span className="text-[12.5px] text-danger-fg">{item.error}</span>
                ) : (
                  <span className="h-1 overflow-hidden rounded-full bg-surface-2">
                    <span className="block h-full rounded-full bg-accent transition-[width]" style={{ width: `${Math.max(4, item.progress * 100)}%` }} />
                  </span>
                )}
              </div>
              {item.error && !item.final && (
                <button type="button" className={buttonClass('ghost', 'sm')} onClick={() => send(item)}>
                  {t('retry')}
                </button>
              )}
              <button
                type="button"
                aria-label={item.error ? t('removeFile', { name: item.file.name }) : t('cancelUpload')}
                onClick={() => (item.error ? setPending((current) => current.filter((entry) => entry.key !== item.key)) : item.abort?.())}
                className="grid size-7 place-items-center rounded-md text-fg-3 hover:bg-surface-2 hover:text-fg"
              >
                <Icon name="x" />
              </button>
            </li>
          ))}
        </ul>
      )}
    </FieldFrame>
  );
}
