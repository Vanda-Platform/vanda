import { Link } from '@inertiajs/react';
import { useState, type KeyboardEvent, type ReactNode } from 'react';
import { moveTile, storedLayout, storeLayout } from '../filesNav';
import { t } from '../i18n';
import { isInternal, nodeAttributes, useSnapshot } from '../store';
import { cx, Icon, Spinner } from '../ui';
import type { UiNode } from '../types';
import { useActivate, type ActivatableNode } from './actions';
import { NodeView } from './NodeView';

interface Folder {
  name: string;
  href: string;
  meta?: string;
  hint?: string;
}

interface FileItem {
  key: string;
  name: string;
  /** An image's own address: its thumbnail (never SVG). */
  thumb?: string;
  kind: string;
  extension?: string;
  meta?: string;
  hint?: string;
  /** Files::fileAction(): what a click on the file runs. */
  action?: ActivatableNode;
}

type FilesNode = UiNode & {
  layout?: 'grid' | 'list';
  folders?: Folder[];
  files?: FileItem[];
  selected?: string;
  emptyText?: string;
};

type Layout = 'grid' | 'list';

/** The colour of a file type's badge (Files::kind()); the extension on it is white. */
const KIND_COLORS: Record<string, string> = {
  pdf: '#D93036',
  doc: '#2F6FE0',
  sheet: '#15803D',
  slides: '#D9480F',
  archive: '#6B635C',
  video: '#7C3AED',
  audio: '#C2185B',
  image: '#0E8A87',
  other: '#5B6B80',
};

function FolderIcon({ className }: { className?: string }) {
  return (
    <svg viewBox="0 0 64 52" aria-hidden="true" className={className}>
      <path d="M4 8a4 4 0 0 1 4-4h15.5a4 4 0 0 1 3 1.4L30 9.5h26a4 4 0 0 1 4 4V44a4 4 0 0 1-4 4H8a4 4 0 0 1-4-4z" fill="var(--v-accent)" opacity=".22" />
      <path d="M4 16a4 4 0 0 1 4-4h48a4 4 0 0 1 4 4v28a4 4 0 0 1-4 4H8a4 4 0 0 1-4-4z" fill="var(--v-accent)" opacity=".55" />
    </svg>
  );
}

/** A sheet with a folded corner and the extension on a coloured badge. */
function DocIcon({ kind, extension, className }: { kind: string; extension?: string; className?: string }) {
  const label = extension ?? '';
  const width = label.length > 3 ? 34 : 30;
  return (
    <svg viewBox="0 0 46 56" aria-hidden="true" className={className}>
      <path d="M6 2h24l14 14v34a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V6a4 4 0 0 1 4-4z" fill="var(--v-surface)" stroke="var(--v-line-strong)" strokeWidth="1.5" />
      <path d="M30 2v10a4 4 0 0 0 4 4h10" fill="none" stroke="var(--v-line-strong)" strokeWidth="1.5" />
      {label && (
        <>
          <rect x="0" y="30" width={width} height="14" rx="3" fill={KIND_COLORS[kind] ?? KIND_COLORS.other} />
          <text x={width / 2} y="40.5" textAnchor="middle" fontFamily="ui-sans-serif,system-ui,sans-serif" fontSize="8.5" fontWeight="700" fill="#fff">
            {label}
          </text>
        </>
      )}
    </svg>
  );
}

/** An image's thumbnail, or the icon of its type when there is none or it fails to load. */
function Preview({ file, small }: { file: FileItem; small?: boolean }) {
  const [broken, setBroken] = useState(false);
  if (file.thumb && !broken) {
    return <img src={file.thumb} alt="" loading="lazy" decoding="async" onError={() => setBroken(true)} className="size-full object-cover" />;
  }
  return <DocIcon kind={file.kind} extension={file.extension} className={small ? 'h-[26px] w-[22px]' : 'h-14 w-[46px]'} />;
}

const focusRing = 'outline-none focus-visible:outline-2 focus-visible:outline-solid focus-visible:outline-accent';

/**
 * What a tile is: a folder's link, a file's link or button (its fileAction()), or nothing to press.
 * Only links and buttons carry data-files-tile, so the arrow keys skip what cannot be opened.
 */
function TileShell({
  href,
  action,
  onActivate,
  current,
  className,
  children,
}: {
  href?: string;
  action?: ActivatableNode;
  onActivate?: (event: { metaKey: boolean; ctrlKey: boolean; shiftKey: boolean; button?: number }) => void;
  current?: boolean;
  className: string;
  children: ReactNode;
}) {
  const link = href ?? (action?.type === 'link' ? action.href : undefined);
  const external = action?.type === 'link' && action.external;

  if (link) {
    return isInternal(link) && !external ? (
      <Link href={link} data-files-tile="" aria-current={current || undefined} className={cx(className, focusRing)}>
        {children}
      </Link>
    ) : (
      <a href={link} data-files-tile="" aria-current={current || undefined} target={external ? '_blank' : undefined} rel={external ? 'noopener noreferrer' : undefined} className={cx(className, focusRing)}>
        {children}
      </a>
    );
  }

  if (action && onActivate) {
    return (
      <button
        type="button"
        data-files-tile=""
        aria-current={current || undefined}
        aria-haspopup={action.type === 'modaltoggle' ? 'dialog' : undefined}
        onClick={(event) => onActivate(event)}
        className={cx(className, focusRing, 'text-left')}
      >
        {children}
      </button>
    );
  }

  return (
    <div aria-current={current || undefined} className={className}>
      {children}
    </div>
  );
}

/**
 * Layout::files(): folders and files as tiles or a list. A folder opens its address; a file runs its
 * fileAction() (a modal, a button, a link) like a table row runs rowAction(). The view the user picks
 * on the toolbar is remembered per node id; the arrow keys move between tiles.
 */
export function FilesView({ node }: { node: FilesNode }) {
  const folders = node.folders ?? [];
  const files = node.files ?? [];
  const [layout, setLayout] = useState<Layout>(() => storedLayout(node.id) ?? (node.layout === 'list' ? 'list' : 'grid'));
  const { activate, dialog } = useActivate();
  const running = useSnapshot((s) => s.running);
  const busy = running !== null;
  const empty = folders.length === 0 && files.length === 0;

  const choose = (next: Layout) => {
    setLayout(next);
    storeLayout(node.id, next);
  };

  const onKeyDown = (event: KeyboardEvent<HTMLDivElement>) => {
    const target = event.target as HTMLElement;
    if (!target.hasAttribute('data-files-tile') || event.altKey || event.ctrlKey || event.metaKey) return;
    const tiles = [...event.currentTarget.querySelectorAll<HTMLElement>('[data-files-tile]')];
    const next = moveTile(
      tiles.map((tile) => tile.getBoundingClientRect()),
      tiles.indexOf(target),
      event.key,
    );
    if (next === null) return;
    event.preventDefault();
    tiles[next]?.focus();
  };

  const open = (file: FileItem) => (event: Parameters<typeof activate>[1]) => {
    if (file.action && !busy) activate(file.action, event);
  };

  const fileTile = (file: FileItem) => {
    const selected = node.selected === file.key;
    const spinning = file.action !== undefined && running === file.action.id;
    const clickable = Boolean(file.action);

    if (layout === 'list') {
      return (
        <li key={file.key}>
          <TileShell
            action={file.action}
            onActivate={open(file)}
            current={selected}
            className={cx(
              'flex w-full min-w-0 items-center gap-3 px-3.5 py-2',
              clickable && 'cursor-pointer hover:bg-surface-2',
              selected && 'bg-accent-soft hover:bg-accent-soft',
              'focus-visible:-outline-offset-2',
            )}
          >
            <span className="grid size-9 shrink-0 place-items-center overflow-hidden rounded-lg bg-surface-2">
              <Preview file={file} small />
            </span>
            <ListText name={file.name} hint={file.hint} meta={file.meta} />
            {spinning && <Spinner />}
          </TileShell>
        </li>
      );
    }

    return (
      <li key={file.key} className="min-w-0">
        <TileShell
          action={file.action}
          onActivate={open(file)}
          current={selected}
          className={cx(
            'relative flex h-full w-full min-w-0 flex-col gap-1.5 rounded-xl border bg-surface p-2 pb-2.5 transition',
            selected ? 'border-accent ring-3 ring-accent-soft' : 'border-line',
            clickable && 'cursor-pointer hover:border-line-strong hover:shadow-sm',
            'focus-visible:outline-offset-2',
          )}
        >
          <span className="grid aspect-[4/3] place-items-center overflow-hidden rounded-lg bg-surface-2">
            <Preview file={file} />
          </span>
          <TileText name={file.name} meta={file.meta} hint={file.hint} />
          {spinning && (
            <span className="absolute top-3 right-3 grid size-6 place-items-center rounded-full bg-surface shadow-sm">
              <Spinner />
            </span>
          )}
        </TileShell>
      </li>
    );
  };

  const folderTile = (folder: Folder, index: number) =>
    layout === 'list' ? (
      <li key={`${index}-${folder.href}`}>
        <TileShell href={folder.href} className="flex w-full min-w-0 items-center gap-3 px-3.5 py-2 hover:bg-surface-2 focus-visible:-outline-offset-2">
          <span className="grid size-9 shrink-0 place-items-center rounded-lg bg-surface-2">
            <FolderIcon className="h-[22px] w-[27px]" />
          </span>
          <ListText name={folder.name} hint={folder.hint} meta={folder.meta} />
        </TileShell>
      </li>
    ) : (
      <li key={`${index}-${folder.href}`} className="min-w-0">
        <TileShell
          href={folder.href}
          className="flex h-full w-full min-w-0 flex-col gap-1.5 rounded-xl border border-line bg-surface p-2 pb-2.5 transition hover:border-line-strong hover:shadow-sm focus-visible:outline-offset-2"
        >
          <span className="grid aspect-[4/3] place-items-center rounded-lg bg-surface-2">
            <FolderIcon className="h-[52px] w-16" />
          </span>
          <TileText name={folder.name} meta={folder.meta} hint={folder.hint} />
        </TileShell>
      </li>
    );

  // At least two tiles across, even in a narrow block or on a phone.
  const gridClass = 'grid grid-cols-[repeat(auto-fill,minmax(min(150px,calc(50%_-_6px)),1fr))] gap-3';

  // Files::toolbar(): fields on the line of the view switch; a field's label is for screen readers there.
  const toolbar = node.children ?? [];

  return (
    <div className="@container flex min-w-0 flex-col gap-3" {...nodeAttributes(node)}>
      {(toolbar.length > 0 || !empty) && (
        <div className="flex items-start gap-2">
          {toolbar.length > 0 && (
            <div className="flex min-w-0 flex-1 flex-wrap items-start gap-2">
              {toolbar.map((child) => (
                <div key={child.id} className="min-w-0 flex-1 basis-56 @2xl:max-w-md">
                  <NodeView node={{ ...child, labelHidden: true }} />
                </div>
              ))}
            </div>
          )}
          {!empty && (
            <div role="group" aria-label={t('filesView')} className="ml-auto inline-flex h-(--v-control) shrink-0 gap-0.5 rounded-lg border border-line bg-surface-2 p-0.5">
              {(['grid', 'list'] as const).map((option) => (
                <button
                  key={option}
                  type="button"
                  aria-pressed={layout === option}
                  aria-label={t(option === 'grid' ? 'filesGrid' : 'filesList')}
                  title={t(option === 'grid' ? 'filesGrid' : 'filesList')}
                  onClick={() => choose(option)}
                  className={cx(
                    'grid w-8 place-items-center rounded-md text-fg-3 hover:text-fg',
                    focusRing,
                    layout === option && 'bg-surface text-fg shadow-sm ring-1 ring-line',
                  )}
                >
                  <Icon name={option === 'grid' ? 'grid' : 'list'} />
                </button>
              ))}
            </div>
          )}
        </div>
      )}

      {empty ? (
        <div className="flex flex-col items-center gap-2 rounded-xl border border-dashed border-line-strong px-4 py-9 text-center text-[14px] text-fg-3">
          <FolderIcon className="h-[52px] w-16" />
          <span>{node.emptyText ?? t('empty')}</span>
        </div>
      ) : (
        <div className="flex flex-col gap-5" onKeyDown={onKeyDown}>
          {layout === 'list' ? (
            <ul className="flex flex-col divide-y divide-line overflow-hidden rounded-xl border border-line bg-surface">
              {folders.map(folderTile)}
              {files.map(fileTile)}
            </ul>
          ) : (
            <>
              {folders.length > 0 && (
                <section className="flex flex-col gap-2.5" aria-label={t('folders')}>
                  {files.length > 0 && <h3 className="text-[12px] font-semibold tracking-[0.04em] text-fg-3 uppercase">{t('folders')}</h3>}
                  <ul className={gridClass}>{folders.map(folderTile)}</ul>
                </section>
              )}
              {files.length > 0 && (
                <section className="flex flex-col gap-2.5" aria-label={t('files')}>
                  {folders.length > 0 && <h3 className="text-[12px] font-semibold tracking-[0.04em] text-fg-3 uppercase">{t('files')}</h3>}
                  <ul className={gridClass}>{files.map(fileTile)}</ul>
                </section>
              )}
            </>
          )}
        </div>
      )}

      {dialog}
    </div>
  );
}

/** Name, then meta (size · date) and hint (where it lies) under it, cut with «…». */
function TileText({ name, meta, hint }: { name: string; meta?: string; hint?: string }) {
  return (
    <>
      <span className="line-clamp-2 text-[13.5px] leading-snug font-medium [overflow-wrap:anywhere]" title={name}>
        {name}
      </span>
      {meta && <span className="truncate text-[12px] text-fg-3">{meta}</span>}
      {hint && <span className="truncate text-[12px] text-fg-2">{hint}</span>}
    </>
  );
}

/** A list row's text: the name and the hint; meta at the end of a wide row, under the name of a narrow one. */
function ListText({ name, hint, meta }: { name: string; hint?: string; meta?: string }) {
  const narrow = [hint, meta].filter(Boolean).join(' · ');
  return (
    <>
      <span className="flex min-w-0 flex-1 flex-col">
        <span className="truncate text-[14px] font-medium" title={name}>
          {name}
        </span>
        {hint && <span className="hidden truncate text-[12.5px] text-fg-2 @lg:block">{hint}</span>}
        {narrow && <span className="truncate text-[12.5px] text-fg-3 @lg:hidden">{narrow}</span>}
      </span>
      {meta && <span className="hidden shrink-0 text-[12.5px] whitespace-nowrap text-fg-3 tabular-nums @lg:block">{meta}</span>}
    </>
  );
}
