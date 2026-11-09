import { useEffect, useId, useMemo, useRef, useState, type KeyboardEvent, type PointerEvent } from 'react';
import { t } from '../i18n';
import { nodeAttributes, useStore } from '../store';
import { applyMove, branch, dropMove, hasBranches, hasChildren, keyboardMove, type DropZone, type Move } from '../treeMove';
import { buttonClass, controlClass, cx, Icon, Kbd } from '../ui';
import type { ActionRef, FieldNode, UiNode } from '../types';
import { DropDownView, type ActivatableNode } from './actions';
import { FieldFrame, useField } from './fields';
import { NodeView } from './NodeView';
import { cellText, opensSomething, rowClickHandlers, RowOpenCell, useRowOpener } from './rows';
import { Guides, TreePicker, type TreeOption } from './treePicker';

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
  /** TreeTable::rowLink(): Enter or a click on the row opens this address. */
  link?: string;
  /** TreeTable::rowAction(): Enter or a click on the row runs this node (a modal toggle, a button or a link). */
  action?: ActivatableNode;
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
  const hintId = useId();
  // One Tab stop for the whole tree (the row last focused, else the first); arrows move between rows.
  const [active, setActive] = useState<string | null>(null);
  const opener = useRowOpener();

  // Fresh rows from the server (after a save, a refresh or a visit) replace the local order.
  useEffect(() => setItems(node.rows ?? []), [node.rows]);

  const byKey = useMemo(() => new Map(items.map((row) => [row.key, row])), [items]);
  const firstColumn = columns[0]?.name;
  // A flat list (no row has children) needs no «Expand all», «Collapse all» or room for the arrows;
  // read from the rows as they are now, so a move that nests a row brings them back.
  const nested = hasBranches(items);

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

  const tabbable = visible.some((row) => row.key === active) ? active : (visible[0]?.key ?? null);

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
      case 'Home':
        event.preventDefault();
        if (visible[0]) focusRow(visible[0].key);
        break;
      case 'End':
        event.preventDefault();
        if (visible.length > 0) focusRow(visible[visible.length - 1]!.key);
        break;
      case 'Enter':
        // A link: with Cmd/Ctrl or Shift the page opens in a new tab, as a link would. A modal or a button runs.
        if (opensSomething(row)) {
          event.preventDefault();
          opener.open(row, event);
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
      {(node.searchable || nested) && (
        <div className="flex flex-wrap items-center gap-2 px-(--v-gutter) pb-3.5">
          {node.searchable && (
            <div className="relative flex w-[300px] max-w-full items-center">
              <Icon name="search" className="pointer-events-none absolute left-2.5 size-4 text-fg-3" />
              <input type="search" value={query} placeholder={t('find')} aria-label={t('find')} onChange={(event) => setQuery(event.target.value)} className={cx(controlClass, 'pl-8')} />
            </div>
          )}
          <div className="flex-grow" />
          {nested && (
            <>
              <button type="button" className={buttonClass('ghost', 'sm')} onClick={() => setOpen(new Set(items.filter((row) => hasChildren(items, row.key)).map((row) => row.key)))}>
                <Icon name="chevron-down" />
                {t('expandAll')}
              </button>
              <button type="button" className={buttonClass('ghost', 'sm')} onClick={() => setOpen(new Set())}>
                <Icon name="chevron-up" />
                {t('collapseAll')}
              </button>
            </>
          )}
        </div>
      )}

      {/* relative: hidden helpers inside rows stay in this scroller instead of widening the page. */}
      <div className="relative overflow-x-auto">
        <p id={hintId} hidden>
          {t('treeKeys')} {move && t('treeKeysMove')}
        </p>
        <table role="treegrid" aria-label={columns[0]?.label} aria-describedby={hintId} className="w-full border-separate border-spacing-0 text-[14px]">
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
                    'px-3 py-[9px] text-[12.5px] font-medium whitespace-nowrap text-fg-3 last:pr-5 compact:py-1.5',
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
                'relative border-t border-line py-(--v-cell-y) align-middle',
                target === 'inside' ? 'bg-accent-soft' : 'group-hover:bg-surface-2/60 group-focus-visible:bg-surface-2/60',
              );

              return (
                <tr
                  key={row.key}
                  data-key={row.key}
                  tabIndex={row.key === tabbable ? 0 : -1}
                  aria-level={row.depth + 1}
                  aria-expanded={branchRow ? isOpen : undefined}
                  onKeyDown={(event) => onRowKey(event, row)}
                  onFocus={() => setActive(row.key)}
                  {...rowClickHandlers(row, opener.open)}
                  // A 2px ring inside the row: the tint alone is too faint to find the focus by.
                  className={cx('group outline-none focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-accent focus-visible:outline-solid', opensSomething(row) && 'cursor-pointer', dragging === row.key && 'opacity-40')}
                >
                  {move && (
                    <td className={cx(cellClass, 'pl-5 text-fg-3')}>
                      {/* Out of the Tab order: Alt + arrows move the focused row (the tree's description says so). */}
                      <button
                        type="button"
                        tabIndex={-1}
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
                    // The name is a real link for the mouse (Cmd/Ctrl-click, «open in a new tab»); the keyboard opens the row with Enter.
                    const content = cell ? (
                      index === 0 && opensSomething(row) ? (
                        <span className="whitespace-nowrap">
                          <RowOpenCell cell={cell} row={row} open={opener.open} tabIndex={-1} openLabel={t('openRow')} />
                        </span>
                      ) : index === 0 && cell.type === 'text' ? (
                        <span className="font-medium whitespace-nowrap text-fg">{String(cell.text)}</span>
                      ) : (
                        <NodeView node={cell} compact />
                      )
                    ) : null;

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
                              nested && <span aria-hidden="true" className="w-[26px] shrink-0" />
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
                  {hasActions && (
                    <td className={cx(cellClass, 'pr-4 text-right')}>
                      {row.actions && <DropDownView node={row.actions} compact name={cellText(row.cells[firstColumn ?? '']) || undefined} tabbable={row.key === tabbable} />}
                    </td>
                  )}
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

      {opener.dialog}

      {move && items.length > 0 && (
        <footer className="flex flex-wrap items-center gap-1.5 border-t border-line px-5 py-3.5 text-[13px] text-fg-3 compact:py-2.5">
          {nested && (
            <>
              <Kbd>→</Kbd>
              <Kbd>←</Kbd> {t('expandCollapse')}
              <span className="mx-1.5">·</span>
            </>
          )}
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

/** Select::tree(): one value with its path in the trigger, or several with branch checkboxes. */
export function TreeSelectField({ node }: { node: FieldNode & { options?: TreeOption[]; multiple?: boolean } }) {
  const id = useId();
  const field = useField(node);

  return (
    <FieldFrame node={node} inputId={id} required={field.required} error={field.error}>
      <TreePicker
        id={id}
        label={node.label ?? node.path}
        options={node.options ?? []}
        value={field.value}
        onChange={(value) => field.setValue(value)}
        multiple={Boolean(node.multiple)}
        placeholder={node.placeholder}
        clearable={!field.required}
        disabled={field.disabled}
        invalid={Boolean(field.error)}
      />
    </FieldFrame>
  );
}
