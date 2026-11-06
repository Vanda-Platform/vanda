import { router } from '@inertiajs/react';
import { useEffect, useId, useMemo, useRef, useState, type KeyboardEvent, type PointerEvent } from 'react';
import { Button as AriaButton, Dialog, DialogTrigger, Popover } from 'react-aria-components';
import { t } from '../i18n';
import { isInternal, nodeAttributes, useStore } from '../store';
import { applyMove, branch, dropMove, hasChildren, keyboardMove, type DropZone, type Move } from '../treeMove';
import { buttonClass, controlClass, cx, Icon, invalidClass, Kbd } from '../ui';
import type { ActionRef, FieldNode, UiNode } from '../types';
import { DropDownView } from './actions';
import { FieldFrame, useField } from './fields';
import { NodeView } from './NodeView';

interface Column {
  name: string;
  label: string;
  align?: 'right' | 'center';
  width?: string;
  muted?: boolean;
}

interface TreeRow {
  key: string;
  parent?: string | null;
  depth: number;
  link?: string;
  cells: Record<string, UiNode>;
  actions?: UiNode;
}

type TreeTableNode = UiNode & {
  columns?: Column[];
  rows?: TreeRow[];
  expandDepth?: number;
  searchable?: boolean;
  move?: ActionRef;
  emptyText?: string;
};

const INDENT = 24;

/** Vertical guide lines, one per level, as in the design. */
function Guides({ depth }: { depth: number }) {
  return Array.from({ length: depth }, (_, index) => (
    <span
      key={index}
      aria-hidden="true"
      className="-my-3 w-6 shrink-0 self-stretch"
      style={{ background: 'linear-gradient(var(--v-line-strong), var(--v-line-strong)) center / 1px 100% no-repeat' }}
    />
  ));
}

const cellText = (cell: UiNode | undefined) => String(cell?.text ?? cell?.label ?? cell?.name ?? '');

function useOpenKeys(node: UiNode, rows: TreeRow[], expandDepth: number) {
  const storageKey = `vanda:tree:${window.location.pathname}:${node.id}`;
  const [open, setOpen] = useState<Set<string>>(() => {
    try {
      const saved = localStorage.getItem(storageKey);
      if (saved) return new Set(JSON.parse(saved) as string[]);
    } catch {
      // Storage is unavailable: start from the default depth.
    }
    return new Set(rows.filter((row) => row.depth < expandDepth).map((row) => row.key));
  });

  const save = (next: Set<string>) => {
    setOpen(next);
    try {
      localStorage.setItem(storageKey, JSON.stringify([...next]));
    } catch {
      // Not remembered, still applied.
    }
  };

  return [open, save] as const;
}

export function TreeTableView({ node }: { node: TreeTableNode }) {
  const store = useStore();
  const columns = node.columns ?? [];
  const [items, setItems] = useState<TreeRow[]>(node.rows ?? []);
  const [open, setOpen] = useOpenKeys(node, items, node.expandDepth ?? 1);
  const [query, setQuery] = useState('');
  const [dragging, setDragging] = useState<string | null>(null);
  const [drop, setDrop] = useState<{ key: string; zone: DropZone } | null>(null);
  const [pointer, setPointer] = useState<{ x: number; y: number } | null>(null);
  const tbody = useRef<HTMLTableSectionElement>(null);
  const hasActions = items.some((row) => row.actions);
  const move = node.move;

  // Fresh rows from the server (after a save, a refresh or a visit) replace the local order.
  useEffect(() => setItems(node.rows ?? []), [node.rows]);

  const byKey = useMemo(() => new Map(items.map((row) => [row.key, row])), [items]);
  const firstColumn = columns[0]?.name;

  const visible = useMemo(() => {
    const needle = query.trim().toLocaleLowerCase();
    if (needle) {
      const shown = new Set<string>();
      for (const row of items) {
        if (!cellText(row.cells[firstColumn ?? '']).toLocaleLowerCase().includes(needle)) continue;
        for (let current: TreeRow | undefined = row; current; current = current.parent ? byKey.get(current.parent) : undefined) shown.add(current.key);
      }
      return items.filter((row) => shown.has(row.key));
    }
    return items.filter((row) => {
      for (let parent = row.parent ? byKey.get(row.parent) : undefined; parent; parent = parent.parent ? byKey.get(parent.parent) : undefined) {
        if (!open.has(parent.key)) return false;
      }
      return true;
    });
  }, [items, open, query, byKey, firstColumn]);

  const toggle = (key: string, value?: boolean) => {
    const next = new Set(open);
    if (value ?? !next.has(key)) next.add(key);
    else next.delete(key);
    setOpen(next);
  };

  const commit = (change: Move | null) => {
    if (!change || !move) return;
    const before = items;
    const after = applyMove(items, change);
    if (after === before) return;
    setItems(after);
    if (change.parent) setOpen(new Set([...open, change.parent]));
    void store.runAction(`${node.id}.move`, move, undefined, { id: change.key, parent: change.parent, position: change.position }).then((ok) => {
      if (!ok) setItems(before);
    });
  };

  const focusRow = (key: string) => requestAnimationFrame(() => tbody.current?.querySelector<HTMLElement>(`tr[data-key="${CSS.escape(key)}"]`)?.focus());

  const onRowKey = (event: KeyboardEvent<HTMLTableRowElement>, row: TreeRow) => {
    if (event.target !== event.currentTarget) return;
    const index = visible.findIndex((entry) => entry.key === row.key);
    const branchRow = hasChildren(items, row.key);

    if (event.altKey && move) {
      const direction = ({ ArrowUp: 'up', ArrowDown: 'down', ArrowRight: 'in', ArrowLeft: 'out' } as const)[event.key as 'ArrowUp'];
      if (direction) {
        event.preventDefault();
        commit(keyboardMove(items, row.key, direction));
        focusRow(row.key);
      }
      return;
    }

    switch (event.key) {
      case 'ArrowDown':
        event.preventDefault();
        if (visible[index + 1]) focusRow(visible[index + 1]!.key);
        break;
      case 'ArrowUp':
        event.preventDefault();
        if (visible[index - 1]) focusRow(visible[index - 1]!.key);
        break;
      case 'ArrowRight':
        event.preventDefault();
        if (branchRow && !open.has(row.key)) toggle(row.key, true);
        break;
      case 'ArrowLeft':
        event.preventDefault();
        if (branchRow && open.has(row.key)) toggle(row.key, false);
        else if (row.parent) focusRow(row.parent);
        break;
      case 'Enter':
        if (row.link) {
          if (isInternal(row.link)) router.visit(row.link);
          else window.location.assign(row.link);
        }
        break;
    }
  };

  // Dragging uses pointer events, so it works with a mouse, a pen and a finger alike.
  const startDrag = (event: PointerEvent<HTMLButtonElement>, row: TreeRow) => {
    if (!move || event.button !== 0) return;
    event.preventDefault();
    event.currentTarget.setPointerCapture(event.pointerId);
    setDragging(row.key);
    setPointer({ x: event.clientX, y: event.clientY });
  };

  const dragMove = (event: PointerEvent<HTMLButtonElement>) => {
    if (!dragging) return;
    setPointer({ x: event.clientX, y: event.clientY });
    const target = document.elementFromPoint(event.clientX, event.clientY)?.closest<HTMLElement>('tr[data-key]');
    const key = target?.dataset.key;
    if (!target || !key || !tbody.current?.contains(target) || branch(items, dragging).has(key)) {
      setDrop(null);
      return;
    }
    const rect = target.getBoundingClientRect();
    const y = (event.clientY - rect.top) / rect.height;
    const zone: DropZone = y < 0.25 ? 'before' : y > 0.75 ? 'after' : 'inside';
    if (drop?.key !== key || drop.zone !== zone) setDrop({ key, zone });
  };

  const endDrag = (commitDrop: boolean) => {
    const target = drop ? byKey.get(drop.key) : undefined;
    if (commitDrop && dragging && drop && target) commit(dropMove(items, dragging, target, drop.zone));
    setDragging(null);
    setDrop(null);
    setPointer(null);
  };


  return (
    <div className="-mx-(--v-gutter) flex flex-col" {...nodeAttributes(node)}>
      <div className="flex flex-wrap items-center gap-2 px-(--v-gutter) pb-3.5">
        {node.searchable && (
          <div className="relative flex w-[300px] max-w-full items-center">
            <Icon name="search" className="pointer-events-none absolute left-2.5 size-4 text-fg-3" />
            <input type="search" value={query} placeholder={t('find')} aria-label={t('find')} onChange={(event) => setQuery(event.target.value)} className={cx(controlClass, 'pl-8')} />
          </div>
        )}
        <div className="flex-grow" />
        <button type="button" className={buttonClass('ghost', 'sm')} onClick={() => setOpen(new Set(items.filter((row) => hasChildren(items, row.key)).map((row) => row.key)))}>
          <Icon name="chevron-down" />
          {t('expandAll')}
        </button>
        <button type="button" className={buttonClass('ghost', 'sm')} onClick={() => setOpen(new Set())}>
          <Icon name="chevron-up" />
          {t('collapseAll')}
        </button>
      </div>

      {/* relative: hidden helpers inside rows stay in this scroller instead of widening the page. */}
      <div className="relative overflow-x-auto">
        <table role="treegrid" aria-label={columns[0]?.label} className="w-full border-separate border-spacing-0 text-[14px]">
          <thead>
            <tr>
              {move && (
                <th className="w-10 pl-5">
                  <span className="sr-only">{t('order')}</span>
                </th>
              )}
              {columns.map((column, index) => (
                <th
                  key={column.name}
                  scope="col"
                  style={{ width: column.width }}
                  className={cx(
                    'px-3 py-[9px] text-[12.5px] font-medium whitespace-nowrap text-fg-3 last:pr-5',
                    index === 0 && (move ? 'pl-1' : 'pl-5'),
                    column.align === 'right' ? 'text-right' : column.align === 'center' ? 'text-center' : 'text-left',
                  )}
                >
                  {column.label}
                </th>
              ))}
              {hasActions && (
                <th className="w-14 pr-5">
                  <span className="sr-only">{t('actions')}</span>
                </th>
              )}
            </tr>
          </thead>
          <tbody ref={tbody}>
            {visible.map((row) => {
              const branchRow = hasChildren(items, row.key);
              const isOpen = open.has(row.key) || query !== '';
              const target = drop?.key === row.key ? drop.zone : null;
              const cellClass = cx(
                'relative border-t border-line py-3 align-middle',
                target === 'inside' ? 'bg-accent-soft' : 'group-hover:bg-surface-2/60 group-focus-visible:bg-surface-2/60',
              );

              return (
                <tr
                  key={row.key}
                  data-key={row.key}
                  tabIndex={0}
                  aria-level={row.depth + 1}
                  aria-expanded={branchRow ? isOpen : undefined}
                  onKeyDown={(event) => onRowKey(event, row)}
                  onClick={(event) => {
                    if (row.link && !(event.target as HTMLElement).closest('a,button,input,[role="menu"]')) {
                      if (isInternal(row.link)) router.visit(row.link);
                      else window.location.assign(row.link);
                    }
                  }}
                  className={cx('group outline-none', row.link && 'cursor-pointer', dragging === row.key && 'opacity-40')}
                >
                  {move && (
                    <td className={cx(cellClass, 'pl-5 text-fg-3')}>
                      <button
                        type="button"
                        aria-label={t('move', { label: cellText(row.cells[firstColumn ?? '']) })}
                        onPointerDown={(event) => startDrag(event, row)}
                        onPointerMove={dragMove}
                        onPointerUp={() => endDrag(true)}
                        onPointerCancel={() => endDrag(false)}
                        className="grid size-7 cursor-grab touch-none place-items-center rounded-md hover:bg-surface-2 hover:text-fg active:cursor-grabbing"
                      >
                        <Icon name="grip" />
                      </button>
                    </td>
                  )}
                  {columns.map((column, index) => {
                    const cell = row.cells[column.name];
                    const content = cell ? index === 0 && cell.type === 'text' ? <span className="font-medium whitespace-nowrap text-fg">{String(cell.text)}</span> : <NodeView node={cell} compact /> : null;

                    return (
                      <td
                        key={column.name}
                        className={cx(
                          cellClass,
                          'px-3 whitespace-nowrap last:pr-5',
                          index === 0 && (move ? 'pl-1' : 'pl-5'),
                          column.muted && 'text-fg-3',
                          column.align === 'right' ? 'text-right' : column.align === 'center' ? 'text-center' : 'text-left',
                        )}
                      >
                        {index === 0 ? (
                          <div className="flex min-w-[260px] items-center">
                            <Guides depth={row.depth} />
                            {branchRow ? (
                              <button
                                type="button"
                                tabIndex={-1}
                                aria-label={t(isOpen ? 'collapse' : 'expand', { label: cellText(cell) })}
                                onClick={() => toggle(row.key)}
                                className="mr-0.5 grid size-6 shrink-0 place-items-center rounded-md text-fg-3 hover:bg-surface-2 hover:text-fg"
                              >
                                <Icon name="chevron-right" className={cx('size-4 transition-transform', isOpen && 'rotate-90')} />
                              </button>
                            ) : (
                              <span aria-hidden="true" className="w-[26px] shrink-0" />
                            )}
                            <Icon name={branchRow ? (isOpen ? 'folder-open' : 'folder') : 'file'} className="mr-2.5 size-4 shrink-0 text-fg-3" />
                            {content}
                            {branchRow && !isOpen && (
                              <span className="ml-2 rounded-full bg-surface-2 px-1.5 text-[12px] text-fg-3 tabular-nums">
                                {items.filter((item) => item.parent === row.key).length}
                              </span>
                            )}
                          </div>
                        ) : (
                          content
                        )}
                        {index === 0 && (target === 'before' || target === 'after') && (
                          <span
                            aria-hidden="true"
                            className={cx('pointer-events-none absolute right-0 flex items-center', target === 'before' ? '-top-px' : '-bottom-px')}
                            style={{ left: (move ? 4 : 20) + row.depth * INDENT }}
                          >
                            <span className="size-2 shrink-0 rounded-full border-2 border-accent bg-surface" />
                            <span className="h-0.5 flex-1 rounded-full bg-accent" />
                          </span>
                        )}
                      </td>
                    );
                  })}
                  {hasActions && <td className={cx(cellClass, 'pr-4 text-right')}>{row.actions && <DropDownView node={row.actions} compact />}</td>}
                </tr>
              );
            })}
          </tbody>
        </table>
      </div>

      {visible.length === 0 && <p className="border-t border-line px-8 py-12 text-center text-fg-3">{query ? t('noResults') : (node.emptyText ?? t('empty'))}</p>}

      {dragging && pointer && (
        <div
          aria-hidden="true"
          className="pointer-events-none fixed z-50 flex -rotate-1 items-center gap-2 rounded-lg border border-line bg-surface px-2.5 py-1.5 text-[13.5px] font-medium shadow-pop"
          style={{ left: pointer.x + 14, top: pointer.y - 18 }}
        >
          <Icon name="file" className="size-4 text-fg-3" />
          {cellText(byKey.get(dragging)?.cells[firstColumn ?? ''])}
          {drop && (
            <span className="font-normal text-fg-3">
              → {t(drop.zone === 'before' ? 'dropBefore' : drop.zone === 'after' ? 'dropAfter' : 'dropInside', { label: cellText(byKey.get(drop.key)?.cells[firstColumn ?? '']) })}
            </span>
          )}
        </div>
      )}

      {move && items.length > 0 && (
        <footer className="flex flex-wrap items-center gap-1.5 border-t border-line px-5 py-3.5 text-[13px] text-fg-3">
          <Kbd>→</Kbd>
          <Kbd>←</Kbd> {t('expandCollapse')}
          <span className="mx-1.5">·</span>
          <Kbd>Alt</Kbd>
          <Kbd>↑</Kbd>
          <Kbd>↓</Kbd> {t('order')}
          <span className="mx-1.5">·</span>
          <Kbd>Alt</Kbd>
          <Kbd>→</Kbd>
          <Kbd>←</Kbd> {t('nest')}
        </footer>
      )}
    </div>
  );
}

interface TreeOption {
  value: string | number;
  label: string;
  parent?: string | number;
  disabled?: boolean;
}

/** Select::tree(): one value with its path in the trigger, or several with branch checkboxes. */
export function TreeSelectField({ node }: { node: FieldNode & { options?: TreeOption[]; multiple?: boolean } }) {
  const id = useId();
  const field = useField(node);
  const options = node.options ?? [];
  const items = useMemo(() => options.map((option) => ({ ...option, key: String(option.value), parent: option.parent === undefined ? null : String(option.parent), depth: 0 })), [options]);
  const byKey = useMemo(() => new Map(items.map((item) => [item.key, item])), [items]);
  const depthOf = (key: string) => {
    let depth = 0;
    for (let item = byKey.get(key); item?.parent; item = byKey.get(item.parent)) depth++;
    return depth;
  };
  const [query, setQuery] = useState('');
  const [open, setOpen] = useState<Set<string>>(new Set());

  const multiple = Boolean(node.multiple);
  const chosen = (multiple ? (Array.isArray(field.value) ? field.value : []) : field.value === null || field.value === undefined ? [] : [field.value]).map(String);
  // A chosen branch means everything inside it (options come parents first).
  const selected = new Set(chosen);
  if (multiple) for (const item of items) if (item.parent && selected.has(item.parent) && !item.disabled) selected.add(item.key);

  const path = (key: string) => {
    const labels: string[] = [];
    for (let item = byKey.get(key); item; item = item.parent ? byKey.get(item.parent) : undefined) labels.unshift(item.label);
    return labels;
  };

  // Start with the branches of the chosen values open.
  useEffect(() => {
    const next = new Set<string>();
    for (const key of selected) for (let item = byKey.get(key); item?.parent; item = byKey.get(item.parent)) next.add(item.parent);
    setOpen(next);
    // Only on the first render and when the options change.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [byKey]);

  const needle = query.trim().toLocaleLowerCase();
  const shown = items.filter((item) => {
    if (needle) {
      const keys = branch(items, item.key);
      return [...keys].some((key) => byKey.get(key)?.label.toLocaleLowerCase().includes(needle));
    }
    for (let parent = item.parent; parent; parent = byKey.get(parent)?.parent ?? null) if (!open.has(parent)) return false;
    return true;
  });

  const valueOf = (key: string) => byKey.get(key)?.value ?? key;
  const state = (key: string): 'on' | 'off' | 'mixed' => {
    const keys = [...branch(items, key)].filter((entry) => !byKey.get(entry)?.disabled);
    const on = keys.filter((entry) => selected.has(entry)).length;
    return on === 0 ? 'off' : on === keys.length ? 'on' : 'mixed';
  };

  const check = (key: string) => {
    const keys = [...branch(items, key)].filter((entry) => !byKey.get(entry)?.disabled);
    const next = new Set(selected);
    if (state(key) === 'on') keys.forEach((entry) => next.delete(entry));
    else keys.forEach((entry) => next.add(entry));
    // A parent is on exactly when everything inside it is on.
    for (const item of [...items].reverse()) {
      const children = items.filter((child) => child.parent === item.key && !child.disabled);
      if (children.length > 0 && !item.disabled) {
        if (children.every((child) => next.has(child.key))) next.add(item.key);
        else next.delete(item.key);
      }
    }
    field.setValue(items.filter((item) => next.has(item.key)).map((item) => item.value));
  };

  // Tags: a fully chosen branch shows as one tag.
  const tags = [...selected].filter((key) => {
    const parent = byKey.get(key)?.parent;
    return !(parent && selected.has(parent));
  });

  const trigger = cx(controlClass, 'flex items-center justify-between gap-2 text-left', field.error && invalidClass, multiple && 'h-auto min-h-9 py-1');

  return (
    <FieldFrame node={node} inputId={id} required={field.required} error={field.error}>
      <DialogTrigger onOpenChange={(isOpen) => !isOpen && setQuery('')}>
        <AriaButton id={id} isDisabled={field.disabled} aria-haspopup="tree" className={trigger}>
          {multiple ? (
            <span className="flex flex-wrap gap-1.5">
              {tags.length === 0 && <span className="text-fg-3">{node.placeholder ?? t('choose')}</span>}
              {tags.map((key) => (
                <span key={key} className="inline-flex h-6 items-center rounded-md border border-line bg-surface-2 px-2 text-[13px] font-medium">
                  {byKey.get(key)?.label ?? key}
                </span>
              ))}
            </span>
          ) : selected.size > 0 ? (
            <span className="flex min-w-0 items-center gap-1 truncate">
              {path([...selected][0]!).map((label, index, all) => (
                <span key={index} className="flex items-center gap-1">
                  <span className={index === all.length - 1 ? 'text-fg' : 'text-fg-3'}>{label}</span>
                  {index < all.length - 1 && <Icon name="chevron-right" className="size-3.5 text-fg-3" />}
                </span>
              ))}
            </span>
          ) : (
            <span className="text-fg-3">{node.placeholder ?? t('choose')}</span>
          )}
          <Icon name="chevron-down" className="size-4 shrink-0 text-fg-3" />
        </AriaButton>
        <Popover placement="bottom start" className="w-(--trigger-width) min-w-72 rounded-xl border border-line bg-surface shadow-pop outline-none">
          <Dialog aria-label={node.label ?? node.path} className="flex flex-col outline-none">
            {({ close }) => (
              <>
                <div className="relative border-b border-line p-2">
                  <Icon name="search" className="pointer-events-none absolute top-1/2 left-4.5 size-4 -translate-y-1/2 text-fg-3" />
                  <input autoFocus type="search" value={query} onChange={(event) => setQuery(event.target.value)} placeholder={t('find')} aria-label={t('find')} className={cx(controlClass, 'h-8 pl-8')} />
                </div>
                <div role="tree" aria-multiselectable={multiple || undefined} className="max-h-80 overflow-y-auto p-1">
                  {shown.length === 0 && <p className="px-3 py-6 text-center text-[13.5px] text-fg-3">{t('noResults')}</p>}
                  {shown.map((item) => {
                    const branchItem = hasChildren(items, item.key);
                    const isOpen = open.has(item.key) || needle !== '';
                    const checked = multiple ? state(item.key) : selected.has(item.key) ? 'on' : 'off';
                    return (
                      <div key={item.key} role="treeitem" aria-level={depthOf(item.key) + 1} aria-expanded={branchItem ? isOpen : undefined} aria-selected={checked === 'on'} aria-disabled={item.disabled || undefined} className="flex h-8 items-center">
                        <Guides depth={depthOf(item.key)} />
                        {branchItem ? (
                          <button
                            type="button"
                            aria-label={t(isOpen ? 'collapse' : 'expand', { label: item.label })}
                            onClick={() => setOpen((current) => {
                              const next = new Set(current);
                              if (next.has(item.key)) next.delete(item.key);
                              else next.add(item.key);
                              return next;
                            })}
                            className="grid size-6 shrink-0 place-items-center rounded-md text-fg-3 hover:bg-surface-2 hover:text-fg"
                          >
                            <Icon name="chevron-right" className={cx('size-4 transition-transform', isOpen && 'rotate-90')} />
                          </button>
                        ) : (
                          <span aria-hidden="true" className="w-6 shrink-0" />
                        )}
                        <button
                          type="button"
                          role={multiple ? 'checkbox' : undefined}
                          aria-checked={multiple ? (checked === 'mixed' ? 'mixed' : checked === 'on') : undefined}
                          disabled={item.disabled}
                          onClick={() => {
                            if (multiple) check(item.key);
                            else {
                              field.setValue(valueOf(item.key));
                              close();
                            }
                          }}
                          className={cx(
                            'flex h-8 min-w-0 flex-1 items-center gap-2 rounded-lg px-2 text-left text-[13.5px] hover:bg-surface-2 disabled:cursor-not-allowed disabled:text-fg-3 disabled:hover:bg-transparent',
                            !multiple && checked === 'on' && 'bg-accent-soft text-accent-fg hover:bg-accent-soft',
                          )}
                        >
                          {multiple ? (
                            <span aria-hidden="true" className={cx('grid size-4 shrink-0 place-items-center rounded border', checked === 'off' ? 'border-line-strong bg-surface' : 'border-accent bg-accent text-white')}>
                              {checked === 'on' && <Icon name="check" className="size-3" />}
                              {checked === 'mixed' && <span className="h-0.5 w-2 rounded bg-white" />}
                            </span>
                          ) : (
                            <Icon name={branchItem ? 'folder' : 'file'} className="size-4 shrink-0 text-fg-3" />
                          )}
                          <span className="truncate">{item.label}</span>
                          {!multiple && checked === 'on' && <Icon name="check" className="ml-auto size-4" />}
                        </button>
                      </div>
                    );
                  })}
                </div>
                {(multiple || (!field.required && selected.size > 0)) && (
                  <div className="flex items-center justify-between border-t border-line px-3 py-2 text-[13px] text-fg-3">
                    <span className="tabular-nums">{multiple ? t('selected', { count: selected.size }) : ''}</span>
                    <button type="button" className={buttonClass('ghost', 'sm')} onClick={() => field.setValue(multiple ? [] : null)}>
                      {t('clear')}
                    </button>
                  </div>
                )}
              </>
            )}
          </Dialog>
        </Popover>
      </DialogTrigger>
    </FieldFrame>
  );
}
