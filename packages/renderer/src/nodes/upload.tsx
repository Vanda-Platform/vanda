import { lazy, Suspense, useEffect, useId, useRef, useState, type DragEvent } from 'react';
import { getPath } from '../expr';
import { lang, t } from '../i18n';
import { useStore } from '../store';
import { upload, type UploadResult } from '../transport';
import { Avatar, buttonClass, cx, Icon } from '../ui';
import type { FieldNode } from '../types';
import type { CropPreset } from './cropper';
import { FieldFrame, useField } from './fields';
import { Lightbox } from './lightbox';

// The cropper (and cropperjs) loads only when an image is about to be cropped.
const CropDialog = lazy(() => import('./cropper').then((module) => ({ default: module.CropDialog })));

type UploadNode = FieldNode & {
  /** Upload::avatar(): a round photo with buttons instead of a drop zone. */
  avatar?: { initials: string; hue: number };
  multiple?: boolean;
  accept?: string;
  maxSize: number;
  maxFiles?: number;
  files?: UploadResult[];
  /** Upload::crop(): images are cropped before they are sent. */
  crop?: CropPreset[];
  customCrop?: boolean;
};

/** An attachment id, or its URL with Upload::target(). */
type Value = number | string;

interface Pending {
  key: number;
  file: File;
  progress: number;
  /** The value this upload takes the place of (replace, crop again). */
  replace?: Value;
  error?: string;
  /** The error is about the file itself, so trying again will not help. */
  final?: boolean;
  abort?: () => void;
}

interface Crop {
  src: string;
  name: string;
  mime: string;
  /** A chosen file; missing when an image already in the field is cropped again. */
  file?: File;
  replace?: Value;
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

/** Pictures a canvas can crop: not SVG (vector) and not GIF (animation would be lost). */
const croppable = (mime: string | undefined) => Boolean(mime?.startsWith('image/')) && mime !== 'image/svg+xml' && mime !== 'image/gif';

const toValues = (value: unknown): Value[] => (Array.isArray(value) ? value : value === null || value === undefined || value === '' ? [] : [value]).filter((item): item is Value => typeof item === 'number' || typeof item === 'string');

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

const iconButton = 'grid size-7 place-items-center rounded-md text-fg-3 hover:bg-surface-2 hover:text-fg';

export function UploadField({ node }: { node: UploadNode }) {
  const id = useId();
  const store = useStore();
  const field = useField(node);
  const input = useRef<HTMLInputElement>(null);
  /** The value the next chosen file replaces ("Replace" on a picture, a new avatar). */
  const replacing = useRef<Value | undefined>(undefined);
  const counter = useRef(0);
  const [known, setKnown] = useState<Record<string, UploadResult>>({});
  const [pending, setPending] = useState<Pending[]>([]);
  const [crops, setCrops] = useState<Crop[]>([]);
  const [over, setOver] = useState(false);
  const [viewing, setViewing] = useState<number | null>(null);
  const cropping = Boolean(node.crop?.length);

  // Files described by the server (on load and after a refresh) join the ones uploaded here.
  useEffect(() => setKnown((current) => ({ ...current, ...Object.fromEntries((node.files ?? []).map((file) => [String(file.value), file])) })), [node.files]);

  const currentValues = (): Value[] => toValues(getPath(store.get().page.state, node.path));
  const values = toValues(field.value);
  const busy = pending.filter((item) => !item.error && item.replace === undefined).length + crops.filter((item) => item.replace === undefined).length;
  const room = node.multiple ? (node.maxFiles ?? Infinity) - values.length - busy : values.length === 0 && busy === 0 ? 1 : 0;

  const patch = (key: number, change: Partial<Pending>) => setPending((current) => current.map((item) => (item.key === key ? { ...item, ...change } : item)));

  const put = (value: Value, replace?: Value) => {
    const list = currentValues();
    if (replace !== undefined && list.some((item) => String(item) === String(replace))) {
      const next = list.map((item) => (String(item) === String(replace) ? value : item));
      return store.setValue(node.path, node.multiple ? next : next[0]);
    }
    store.setValue(node.path, node.multiple ? [...list, value] : value);
  };

  const send = (item: Pending) => {
    const request = upload(store.get().page, node.path, item.file, (progress) => patch(item.key, { progress }));
    patch(item.key, { abort: request.abort, error: undefined, progress: 0 });

    request.promise
      .then(({ status, data }) => {
        if (status === 200) {
          const value = data.value;
          setKnown((current) => ({ ...current, [String(value)]: data }));
          setPending((current) => current.filter((entry) => entry.key !== item.key));
          put(value, item.replace);
        } else {
          patch(item.key, { error: data.errors?.[node.path]?.[0] ?? t('uploadFailed'), final: status === 422 });
        }
      })
      .catch((error: unknown) => {
        if (error instanceof DOMException && error.name === 'AbortError') setPending((current) => current.filter((entry) => entry.key !== item.key));
        else patch(item.key, { error: t('uploadFailed') });
      });
  };

  /** Checks a file in the browser (the server checks again) and sends it. */
  const enqueue = (file: File, replace?: Value) => {
    const item: Pending = {
      key: ++counter.current,
      file,
      progress: 0,
      replace,
      ...(file.size > node.maxSize
        ? { error: t('tooLarge', { size: formatSize(file.size), max: formatSize(node.maxSize) }), final: true }
        : !accepts(node.accept, file)
          ? { error: t('wrongType'), final: true }
          : {}),
    };
    setPending((current) => [...current, item]);
    if (!item.error) send(item);
  };

  const start = (files: FileList | null, replace?: Value) => {
    if (!files) return;
    // Read the room from the store: the value may have changed a moment ago, in the same event.
    const free = replace !== undefined ? 1 : node.multiple ? (node.maxFiles ?? Infinity) - currentValues().length - busy : currentValues().length === 0 && busy === 0 ? 1 : 0;
    const chosen = [...files].slice(0, Math.max(0, free));
    // A new attempt in a one-file field makes the old error stale.
    if (!node.multiple) setPending((current) => current.filter((item) => !item.error));
    const toCrop = cropping ? chosen.filter((file) => croppable(file.type) && accepts(node.accept, file)) : [];
    chosen.filter((file) => !toCrop.includes(file)).forEach((file) => enqueue(file, replace));
    setCrops((current) => [...current, ...toCrop.map((file) => ({ src: URL.createObjectURL(file), name: file.name, mime: file.type, file, replace }))]);
    if (input.current) input.current.value = '';
  };

  const finishCrop = (result: File | 'original' | null) => {
    const [item] = crops;
    if (!item) return;
    if (item.file) URL.revokeObjectURL(item.src);
    setCrops((current) => current.slice(1));
    if (result === 'original') {
      if (item.file) enqueue(item.file, item.replace);
    } else if (result) {
      enqueue(result, item.replace);
    }
  };

  const recrop = (value: Value, file: UploadResult) => file.url && setCrops((current) => [...current, { src: file.url!, name: file.name, mime: file.mime, replace: value }]);

  const choose = (replace?: Value) => {
    if (!input.current) return;
    replacing.current = replace;
    input.current.multiple = Boolean(node.multiple) && replace === undefined;
    input.current.click();
  };

  // Pictures of the field open in the viewer; other files keep their link.
  const images = values.map((value) => known[String(value)]).filter((file): file is UploadResult & { url: string } => Boolean(file?.url && file.mime.startsWith('image/')));
  const view = (value: Value) => {
    const index = images.findIndex((file) => String(file.value) === String(value));
    if (index >= 0) setViewing(index);
  };
  const viewer = viewing !== null && <Lightbox images={images} start={viewing} onClose={() => setViewing(null)} />;

  const remove = (value: Value) => {
    const next = currentValues().filter((entry) => String(entry) !== String(value));
    store.setValue(node.path, node.multiple ? next : null);
  };

  const onDrop = (event: DragEvent) => {
    event.preventDefault();
    setOver(false);
    if (!field.disabled) start(event.dataTransfer.files);
  };

  const hint = [node.accept?.split(',').map(typeLabel).join(', '), t('upTo', { size: formatSize(node.maxSize) })].filter(Boolean).join(' · ');

  const fileInput = (
    // The visible button chooses the files (and the field's label still clicks through to it):
    // this input is no stop of Tab and no unnamed field for a screen reader.
    <input
      ref={input}
      id={id}
      type="file"
      tabIndex={-1}
      aria-hidden="true"
      className="sr-only"
      accept={node.accept}
      onClick={(event) => {
        // A click through the field's label adds files; choose() clicks from code.
        if (event.isTrusted) replacing.current = undefined;
      }}
      onChange={(event) => {
        start(event.target.files, replacing.current);
        replacing.current = undefined;
      }}
    />
  );

  const cropDialog = crops[0] && (
    <Suspense fallback={null}>
      <CropDialog
        key={crops[0].src}
        src={crops[0].src}
        name={crops[0].name}
        mime={crops[0].mime}
        presets={node.crop ?? []}
        custom={node.customCrop === true}
        storageKey={`vanda.crop.${node.path.replace(/\.\d+\./g, '.*.')}`}
        onDone={finishCrop}
        onCancel={() => finishCrop(null)}
      />
    </Suspense>
  );

  if (node.avatar) {
    const file = values[0] !== undefined ? known[String(values[0])] : undefined;
    const item = pending[0];
    return (
      <FieldFrame node={node} inputId={id} required={field.required} error={field.error}>
        <div className="flex flex-wrap items-center gap-4">
          <Avatar initials={node.avatar.initials} hue={node.avatar.hue} image={file?.url} size="lg" />
          <div className="flex flex-col gap-1.5">
            {!field.disabled && (
              <div className="flex gap-2">
                <button
                  type="button"
                  onClick={() => choose(values[0])}
                  aria-label={node.label && !(item && !item.error) ? `${t('uploadPhoto')}: ${node.label}` : undefined}
                  className={cx(buttonClass('secondary', 'sm'), item && !item.error && 'pointer-events-none opacity-50')}
                >
                  {item && !item.error ? `${Math.round(item.progress * 100)}%` : t('uploadPhoto')}
                </button>
                {values.length > 0 && (
                  <button type="button" className={buttonClass('ghost', 'sm')} onClick={() => remove(values[0]!)}>
                    {t('remove')}
                  </button>
                )}
              </div>
            )}
            {item?.error && <span className="text-[12.5px] text-danger-fg">{item.error}</span>}
          </div>
          {fileInput}
        </div>
        {cropDialog}
        {viewer}
      </FieldFrame>
    );
  }

  // One picture (a cover, a photo): a large preview with its actions under it.
  const picture = !node.multiple && values.length === 1 ? known[String(values[0])] : undefined;
  const replacingPicture = picture ? pending.find((item) => item.replace !== undefined && String(item.replace) === String(values[0])) : undefined;

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
            <Icon name={cropping ? 'crop' : 'upload'} />
          </span>
          <span className="text-[14px] text-fg-2">
            {/* The field's name goes with the button: its label points at the hidden input. */}
            <button
              type="button"
              onClick={() => choose()}
              aria-label={node.label ? `${t(node.multiple ? 'chooseFiles' : 'chooseFile')}: ${node.label}` : undefined}
              className="cursor-pointer font-medium text-accent-fg underline-offset-4 hover:underline"
            >
              {t(node.multiple ? 'chooseFiles' : 'chooseFile')}
            </button>{' '}
            {t('orDrop')}
          </span>
          <span className="text-[12.5px] text-fg-3">{hint}</span>
        </div>
      )}
      {fileInput}

      {picture?.url && picture.mime.startsWith('image/') ? (
        <div className={cx('@container overflow-hidden rounded-xl border border-line', field.error && 'border-danger')}>
          <div className="grid place-items-center bg-surface-2 bg-[repeating-conic-gradient(var(--v-surface-2)_0_25%,var(--v-surface)_0_50%)] bg-size-[16px_16px] p-3">
            <button type="button" onClick={() => view(values[0]!)} aria-label={t('viewImage', { name: picture.name })} className="cursor-zoom-in rounded-md focus-visible:outline-2 focus-visible:outline-accent">
              <img src={picture.url} alt={picture.name} className="max-h-60 w-auto rounded-md object-contain" />
            </button>
          </div>
          <div className="flex items-center gap-2 border-t border-line px-3 py-2">
            <div className="flex min-w-0 flex-1 flex-col">
              <span className="truncate text-[13.5px] font-medium">{picture.name}</span>
              {replacingPicture ? (
                <span className={cx('text-[12.5px]', replacingPicture.error ? 'text-danger-fg' : 'text-fg-3 tabular-nums')}>
                  {replacingPicture.error ?? `${Math.round(replacingPicture.progress * 100)}%`}
                </span>
              ) : (
                picture.size != null && <span className="text-[12.5px] text-fg-3">{formatSize(picture.size)}</span>
              )}
            </div>
            {!field.disabled && (
              <>
                {/* In a narrow column the buttons keep only their icons, so the file name stays readable. */}
                {cropping && croppable(picture.mime) && (
                  <button type="button" aria-label={t('cropAgain')} title={t('cropAgain')} className={buttonClass('ghost', 'sm')} onClick={() => recrop(values[0]!, picture)}>
                    <Icon name="crop" />
                    <span className="hidden @[30rem]:inline">{t('cropAgain')}</span>
                  </button>
                )}
                <button type="button" aria-label={t('replace')} title={t('replace')} className={buttonClass('ghost', 'sm')} onClick={() => choose(values[0])}>
                  <Icon name="upload" />
                  <span className="hidden @[30rem]:inline">{t('replace')}</span>
                </button>
                <button type="button" aria-label={t('removeFile', { name: picture.name })} onClick={() => remove(values[0]!)} className={iconButton}>
                  <Icon name="trash" />
                </button>
              </>
            )}
          </div>
        </div>
      ) : (
        (values.length > 0 || pending.length > 0) && (
          <ul className="flex flex-col divide-y divide-line rounded-xl border border-line">
            {values.map((value) => {
              const file = known[String(value)];
              const name = file?.name ?? String(value);
              return (
                <li key={String(value)} className="flex items-center gap-3 px-3 py-2.5">
                  {file?.url && file.mime.startsWith('image/') ? (
                    <button type="button" onClick={() => view(value)} aria-label={t('viewImage', { name })} className="shrink-0 cursor-zoom-in rounded-lg">
                      <FileBadge name={name} mime={file.mime} url={file.url} />
                    </button>
                  ) : (
                    <FileBadge name={name} mime={file?.mime} url={file?.url} />
                  )}
                  <div className="flex min-w-0 flex-1 flex-col">
                    {file?.url && file.mime.startsWith('image/') ? (
                      <button type="button" onClick={() => view(value)} className="truncate text-left text-[14px] font-medium text-fg hover:underline">
                        {file.name}
                      </button>
                    ) : file?.url ? (
                      <a href={file.url} target="_blank" rel="noreferrer" className="truncate text-[14px] font-medium text-fg hover:underline">
                        {file.name}
                      </a>
                    ) : (
                      <span className="truncate text-[14px] font-medium">{typeof value === 'number' ? `#${value}` : name}</span>
                    )}
                    {file?.size != null && <span className="text-[12.5px] text-fg-3">{formatSize(file.size)}</span>}
                  </div>
                  {!field.disabled && cropping && file?.url && croppable(file.mime) && (
                    <button type="button" aria-label={`${t('cropAgain')}: ${name}`} title={t('cropAgain')} onClick={() => recrop(value, file)} className={iconButton}>
                      <Icon name="crop" />
                    </button>
                  )}
                  {!field.disabled && (
                    <button type="button" aria-label={t('removeFile', { name })} onClick={() => remove(value)} className={iconButton}>
                      <Icon name="trash" />
                    </button>
                  )}
                </li>
              );
            })}
            {pending
              .filter((item) => item !== replacingPicture)
              .map((item) => (
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
                    className={iconButton}
                  >
                    <Icon name="x" />
                  </button>
                </li>
              ))}
          </ul>
        )
      )}
      {cropDialog}
      {viewer}
    </FieldFrame>
  );
}
