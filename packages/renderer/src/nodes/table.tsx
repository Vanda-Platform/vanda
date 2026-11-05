import { router } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { Button as AriaButton, Dialog, DialogTrigger, Menu, MenuItem, MenuTrigger, Popover } from 'react-aria-components';
import { t } from '../i18n';
import { isInternal, nodeAttributes } from '../store';
import { buttonClass, controlClass, cx, Icon, Kbd } from '../ui';
import type { Option, UiNode } from '../types';
import { ButtonView, DropDownView, type ButtonNode } from './actions';
import { NodeView } from './NodeView';

interface Column {
  name: string;
  label: string;
  align?: 'right' | 'center';
  width?: string;
  muted?: boolean;
  sortable?: boolean;
  hideable?: boolean;
  hidden?: boolean;
}

interface Row {
  key: string;
  link?: string;
  cells: Record<string, UiNode>;
  actions?: UiNode;
}

interface Pagination {
  page: number;
  lastPage: number;
  perPage: number;
  total: number;
  from: number | null;
  to: number | null;
  options?: number[];
}

type FilterValue = string | { start: string | null; end: string | null };

interface Filter {
  name: string;
  label: string;
  kind: 'select' | 'text' | 'daterange';
  options?: Option[];
  value?: FilterValue;
  display?: string;
}

interface View {
  label: string;
  filters: Record<string, FilterValue>;
  count?: number;
  active?: boolean;
}

type TableNode = UiNode & {
  columns?: Column[];
  rows?: Row[];
  pagination?: Pagination;
  emptyText?: string;
  sort?: string;
  search?: { value: string; placeholder?: string };
  filters?: Filter[];
  views?: View[];
  bulk?: ButtonNode[];
  export?: string;
  empty?: UiNode;
};

/** Visit the same screen with changed query parameters, keeping scroll and local state. */
export function visitWith(params: Record<string, string | null>, filters?: Record<string, FilterValue | null>) {
  const url = new URL(window.location.href);
  for (const [key, value] of Object.entries(params)) {
    if (value === null || value === '') url.searchParams.delete(key);
    else url.searchParams.set(key, value);
  }
  if (filters) {
    for (const key of [...url.searchParams.keys()]) if (key.startsWith('filter[')) url.searchParams.delete(key);
    for (const [name, value] of Object.entries(filters)) {
      if (value === null || value === '') continue;
      if (typeof value === 'string') url.searchParams.set(`filter[${name}]`, value);
      else {
        if (value.start) url.searchParams.set(`filter[${name}][start]`, value.start);
        if (value.end) url.searchParams.set(`filter[${name}][end]`, value.end);
      }
    }
    url.searchParams.delete('page');
  }
  router.visit(url.toString(), { preserveScroll: true, preserveState: true, replace: true });
}

const currentFilters = (filters: Filter[]) =>
  Object.fromEntries(filters.filter((filter) => filter.value !== undefined).map((filter) => [filter.name, filter.value!]));

const hiddenKey = (node: UiNode) => `vanda:columns:${window.location.pathname}:${node.id}`;

function useHiddenColumns(node: UiNode, columns: Column[]) {
  const [hidden, setHidden] = useState<string[]>(() => {
    try {
      const saved = localStorage.getItem(hiddenKey(node));
      if (saved) return JSON.parse(saved) as string[];
    } catch {
      // Private mode or blocked storage: fall back to the defaults.
    }
    return columns.filter((column) => column.hidden).map((column) => column.name);
  });

  const toggle = (name: string) =>
    setHidden((current) => {
      const next = current.includes(name) ? current.filter((item) => item !== name) : [...current, name];
      try {
        localStorage.setItem(hiddenKey(node), JSON.stringify(next));
      } catch {
        // Not remembered, still applied.
      }
      return next;
    });

  return [hidden, toggle] as const;
}

export function TableView({ node }: { node: TableNode }) {
  const columns = node.columns ?? [];
  const rows = node.rows ?? [];
  const bulk = node.bulk ?? [];
  const filters = node.filters ?? [];
  const views = node.views ?? [];
  const pagination = node.pagination;
  const hasActions = rows.some((row) => row.actions);
  const [selected, setSelected] = useState<string[]>([]);
  const [query, setQuery] = useState(node.search?.value ?? '');
  const [hidden, toggleColumn] = useHiddenColumns(node, columns);
  const debounce = useRef<ReturnType<typeof setTimeout>>(undefined);
  const searchRef = useRef<HTMLInputElement>(null);
  const visible = columns.filter((column) => !hidden.includes(column.name));
  const active = currentFilters(filters);

  // A new page of rows (search, sort, after an action) drops selections that are gone.
  useEffect(() => setSelected((current) => current.filter((key) => rows.some((row) => row.key === key))), [rows]);

  // "/" focuses the search, as in the design.
  useEffect(() => {
    if (!node.search) return;
    const onKey = (event: KeyboardEvent) => {
      const target = event.target as HTMLElement;
      if (event.key === '/' && !target.closest('input, textarea, [contenteditable="true"]')) {
        event.preventDefault();
        searchRef.current?.focus();
      }
    };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [node.search]);

  const allChecked = rows.length > 0 && rows.every((row) => selected.includes(row.key));
  const toggle = (key: string) => setSelected((current) => (current.includes(key) ? current.filter((k) => k !== key) : [...current, key]));

  const sortBy = (column: string) => {
    const next = node.sort === column ? `-${column}` : node.sort === `-${column}` ? null : column;
    visitWith({ sort: next, page: null });
  };

  const hideable = columns.filter((column) => column.hideable);
  const toolbar = node.search || filters.length > 0 || hideable.length > 0 || node.export;
  const exportUrl = useMemo(() => {
    if (!node.export) return null;
    const url = new URL(window.location.href);
    url.searchParams.delete('page');
    url.searchParams.set('export', node.export);
    return url.toString();
    // The URL changes with every visit, and every visit brings new rows.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [node.export, rows]);

  return (
    <div className="-mx-(--v-gutter) flex flex-col" {...nodeAttributes(node)}>
      {views.length > 0 && (
        <div role="tablist" aria-label={t('views')} className="mx-(--v-gutter) mb-1 flex gap-6 overflow-x-auto border-b border-line">
          {views.map((view) => (
            <button
              key={view.label}
              type="button"
              role="tab"
              aria-selected={Boolean(view.active)}
              onClick={() => visitWith({ q: null }, view.filters)}
              className={cx(
                'flex items-center gap-2 py-2.5 text-[14px] font-medium whitespace-nowrap text-fg-2 hover:text-fg',
                view.active && 'text-fg shadow-[inset_0_-2px_0_var(--v-accent)]',
              )}
            >
              {view.label}
              {view.count !== undefined && (
                <span className={cx('rounded-full px-1.5 text-[12px] tabular-nums', view.active ? 'bg-accent-soft text-accent-fg' : 'bg-surface-2 text-fg-3')}>
                  {view.count.toLocaleString()}
                </span>
              )}
            </button>
          ))}
        </div>
      )}

      {toolbar && selected.length === 0 && (
        <div className="flex flex-wrap items-center gap-2 px-(--v-gutter) py-3.5">
          {node.search && (
            <div className="relative flex w-[300px] max-w-full items-center">
              <Icon name="search" className="pointer-events-none absolute left-2.5 size-4 text-fg-3" />
              <input
                ref={searchRef}
                type="search"
                value={query}
                placeholder={node.search.placeholder ?? t('searchTable')}
                aria-label={node.search.placeholder ?? t('searchTable')}
                onChange={(event) => {
                  const value = event.target.value;
                  setQuery(value);
                  clearTimeout(debounce.current);
                  debounce.current = setTimeout(() => visitWith({ q: value, page: null }), 300);
                }}
                className={cx(controlClass, 'pr-8 pl-8')}
              />
              <span className="pointer-events-none absolute right-2">
                <Kbd>/</Kbd>
              </span>
            </div>
          )}

          {filters
            .filter((filter) => filter.value !== undefined)
            .map((filter) => (
              <span key={filter.name} className="inline-flex h-7 items-center gap-1.5 rounded-full border border-line-strong bg-surface pr-1 pl-2.5 text-[13px]">
                <b className="font-medium text-fg-3">{filter.label}:</b> {filter.display}
                <button
                  type="button"
                  aria-label={t('removeFilter', { label: filter.label })}
                  onClick={() => visitWith({}, { ...active, [filter.name]: null })}
                  className="grid size-5 place-items-center rounded-full text-fg-3 hover:bg-surface-2 hover:text-fg"
                >
                  <Icon name="x" className="size-3.5" />
                </button>
              </span>
            ))}

          {filters.length > 0 && <FilterPopover filters={filters} active={active} />}

          <div className="flex-grow" />

          {hideable.length > 0 && (
            <MenuTrigger>
              <AriaButton className={buttonClass('ghost', 'sm')}>
                <Icon name="columns" />
                {t('columns')}
              </AriaButton>
              <Popover placement="bottom end" className="min-w-48 rounded-xl border border-line bg-surface p-1 shadow-pop outline-none">
                <Menu
                  selectionMode="multiple"
                  selectedKeys={hideable.filter((column) => !hidden.includes(column.name)).map((column) => column.name)}
                  onAction={(key) => toggleColumn(String(key))}
                  className="outline-none"
                >
                  {hideable.map((column) => (
                    <MenuItem
                      key={column.name}
                      id={column.name}
                      className="flex h-8 cursor-pointer items-center gap-2.5 rounded-md px-2 text-[14px] outline-none data-[focused]:bg-surface-2"
                    >
                      {({ isSelected }) => (
                        <>
                          <span className={cx('grid size-4 place-items-center rounded border', isSelected ? 'border-accent bg-accent text-white' : 'border-line-strong')}>
                            {isSelected && <Icon name="check" className="size-3" />}
                          </span>
                          {column.label}
                        </>
                      )}
                    </MenuItem>
                  ))}
                </Menu>
              </Popover>
            </MenuTrigger>
          )}

          {exportUrl && (
            <a href={exportUrl} download className={buttonClass('ghost', 'sm')}>
              <Icon name="download" />
              {t('export')}
            </a>
          )}
        </div>
      )}

      {selected.length > 0 && (
        <div className="mx-5 my-2.5 flex flex-wrap items-center gap-2 rounded-[10px] bg-accent-soft py-1.5 pr-1.5 pl-3.5 text-accent-fg">
          <span className="text-[14px] font-semibold">{t('selected', { count: selected.length })}</span>
          {pagination && <span className="text-[13.5px] opacity-80">{t('ofTotal', { total: pagination.total.toLocaleString() })}</span>}
          <div className="flex-grow" />
          {bulk.map((button) => (
            <ButtonView key={button.id} node={{ ...button, variant: button.variant === 'danger' ? 'danger-soft' : 'secondary' }} size="sm" ids={selected} />
          ))}
          <button type="button" aria-label={t('clearSelection')} onClick={() => setSelected([])} className="grid size-7 place-items-center rounded-md hover:bg-surface/60">
            <Icon name="x" />
          </button>
        </div>
      )}

      <div className="overflow-x-auto">
        <table className="w-full border-separate border-spacing-0 text-[14px]">
          <thead>
            <tr>
              {bulk.length > 0 && (
                <th className="w-11 pl-5">
                  <input
                    type="checkbox"
                    aria-label={t('selectAll')}
                    checked={allChecked}
                    ref={(input) => {
                      if (input) input.indeterminate = selected.length > 0 && !allChecked;
                    }}
                    onChange={() => setSelected(allChecked ? [] : rows.map((row) => row.key))}
                    className="vanda-check"
                  />
                </th>
              )}
              {visible.map((column) => {
                const direction = node.sort === column.name ? 'asc' : node.sort === `-${column.name}` ? 'desc' : null;
                return (
                  <th
                    key={column.name}
                    scope="col"
                    style={{ width: column.width }}
                    aria-sort={direction === 'asc' ? 'ascending' : direction === 'desc' ? 'descending' : undefined}
                    className={cx(
                      'px-3 py-[9px] text-[12.5px] font-medium whitespace-nowrap text-fg-3 first:pl-5 last:pr-5',
                      column.align === 'right' ? 'text-right' : column.align === 'center' ? 'text-center' : 'text-left',
                    )}
                  >
                    {column.sortable ? (
                      <button type="button" onClick={() => sortBy(column.name)} className={cx('group/sort inline-flex items-center gap-1 hover:text-fg', direction && 'text-fg')}>
                        {column.label}
                        <Icon name={direction === 'asc' ? 'arrow-up' : direction === 'desc' ? 'arrow-down' : 'sort'} className={cx('size-3.5', !direction && 'opacity-0 group-hover/sort:opacity-50 group-focus-visible/sort:opacity-50')} />
                      </button>
                    ) : (
                      column.label
                    )}
                  </th>
                );
              })}
              {hasActions && (
                <th className="w-14 pr-5">
                  <span className="sr-only">{t('actions')}</span>
                </th>
              )}
            </tr>
          </thead>
          <tbody>
            {rows.map((row) => {
              const isSelected = selected.includes(row.key);
              const cellClass = cx('border-t border-line py-3 align-middle', isSelected ? 'bg-accent-soft' : 'group-hover:bg-surface-2/60');
              return (
                <tr
                  key={row.key}
                  className={cx('group', row.link && 'cursor-pointer')}
                  onClick={(event) => {
                    if (row.link && !(event.target as HTMLElement).closest('a,button,input,[role="menu"],[role="dialog"]')) {
                      if (isInternal(row.link)) router.visit(row.link);
                      else window.location.assign(row.link);
                    }
                  }}
                >
                  {bulk.length > 0 && (
                    <td className={cx(cellClass, 'pl-5')}>
                      <input type="checkbox" aria-label={t('selectRow')} checked={isSelected} onChange={() => toggle(row.key)} className="vanda-check" />
                    </td>
                  )}
                  {visible.map((column, index) => {
                    const cell = row.cells[column.name];
                    return (
                      <td
                        key={column.name}
                        className={cx(
                          cellClass,
                          'px-3 whitespace-nowrap first:pl-5 last:pr-5',
                          column.muted && 'text-fg-3',
                          column.align === 'right' ? 'text-right' : column.align === 'center' ? 'text-center' : 'text-left',
                        )}
                      >
                        {cell && index === 0 && row.link && cell.type === 'text' ? (
                          <span className="font-medium text-fg">{String(cell.text)}</span>
                        ) : cell ? (
                          <NodeView node={cell} compact />
                        ) : null}
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

      {rows.length === 0 &&
        (node.empty ? (
          <div className="border-t border-line px-(--v-gutter) py-6">
            <NodeView node={node.empty} />
          </div>
        ) : (
          <p className="border-t border-line px-8 py-12 text-center text-fg-3">{node.emptyText ?? t('empty')}</p>
        ))}

      {pagination && (pagination.lastPage > 1 || pagination.options) && pagination.total > 0 && <PaginationBar pagination={pagination} />}
    </div>
  );
}

/** Page numbers around the current one: 1 … 4 5 [6] 7 8 … 50 */
function pageList(page: number, last: number): (number | null)[] {
  const pages = new Set([1, last, page - 1, page, page + 1].filter((n) => n >= 1 && n <= last));
  const sorted = [...pages].sort((a, b) => a - b);
  return sorted.flatMap((n, i) => (i > 0 && n - sorted[i - 1]! > 1 ? [null, n] : [n]));
}

function PaginationBar({ pagination }: { pagination: Pagination }) {
  return (
    <nav aria-label="Pagination" className="flex flex-wrap items-center justify-between gap-3 border-t border-line px-5 py-3.5 text-[13.5px] text-fg-3">
      <span className="tabular-nums">{t('shown', { from: pagination.from ?? 0, to: pagination.to ?? 0, total: pagination.total.toLocaleString() })}</span>
      <span className="flex flex-wrap items-center gap-4">
        {pagination.options && (
          <label className="flex items-center gap-2 whitespace-nowrap text-fg-2">
            {t('perPage')}
            <select
              value={pagination.perPage}
              onChange={(event) => visitWith({ perPage: event.target.value, page: null })}
              className={cx(controlClass, 'h-7 w-[72px] px-2 text-[13px]')}
            >
              {pagination.options.map((option) => (
                <option key={option} value={option}>
                  {option}
                </option>
              ))}
            </select>
          </label>
        )}
        {pagination.lastPage > 1 && (
          <span className="flex items-center gap-0.5">
            <button
              type="button"
              aria-label={t('previous')}
              disabled={pagination.page <= 1}
              onClick={() => visitWith({ page: String(pagination.page - 1) })}
              className="grid size-7 place-items-center rounded-md hover:bg-surface-2 disabled:opacity-40"
            >
              <Icon name="chevron-left" />
            </button>
            {pageList(pagination.page, pagination.lastPage).map((page, index) =>
              page === null ? (
                <span key={`gap-${index}`} className="px-1.5">
                  …
                </span>
              ) : (
                <button
                  key={page}
                  type="button"
                  aria-current={page === pagination.page ? 'page' : undefined}
                  onClick={() => visitWith({ page: String(page) })}
                  className={cx(
                    'grid h-7 min-w-7 place-items-center rounded-md px-2 tabular-nums',
                    page === pagination.page ? 'border border-line-strong bg-surface text-fg shadow-sm' : 'hover:bg-surface-2 hover:text-fg',
                  )}
                >
                  {page}
                </button>
              ),
            )}
            <button
              type="button"
              aria-label={t('next')}
              disabled={pagination.page >= pagination.lastPage}
              onClick={() => visitWith({ page: String(pagination.page + 1) })}
              className="grid size-7 place-items-center rounded-md hover:bg-surface-2 disabled:opacity-40"
            >
              <Icon name="chevron-right" />
            </button>
          </span>
        )}
      </span>
    </nav>
  );
}

/** "+ Filter": every filter of the table in one small form. */
function FilterPopover({ filters, active }: { filters: Filter[]; active: Record<string, FilterValue> }) {
  const [draft, setDraft] = useState<Record<string, FilterValue | null>>(active);
  const set = (name: string, value: FilterValue | null) => setDraft((current) => ({ ...current, [name]: value }));

  return (
    <DialogTrigger onOpenChange={(open) => open && setDraft(active)}>
      <AriaButton className="inline-flex h-7 items-center gap-1 rounded-full border border-dashed border-line-strong px-2.5 text-[13px] text-fg-2 hover:border-fg-3 hover:text-fg">
        <Icon name="plus" className="size-3.5" />
        {t('filter')}
      </AriaButton>
      <Popover placement="bottom start" className="w-80 rounded-xl border border-line bg-surface shadow-pop outline-none">
        <Dialog aria-label={t('filter')} className="outline-none">
          {({ close }) => (
            <form
              className="flex flex-col gap-4 p-4"
              onSubmit={(event) => {
                event.preventDefault();
                close();
                visitWith({}, draft);
              }}
            >
              {filters.map((filter) => {
                const value = draft[filter.name] ?? null;
                const id = `filter-${filter.name}`;
                return (
                  <div key={filter.name} className="flex flex-col gap-1.5">
                    <label htmlFor={id} className="text-[13px] font-medium text-fg-2">
                      {filter.label}
                    </label>
                    {filter.kind === 'select' && (
                      <select id={id} value={typeof value === 'string' ? value : ''} onChange={(event) => set(filter.name, event.target.value || null)} className={controlClass}>
                        <option value="">{t('any')}</option>
                        {(filter.options ?? []).map((option) => (
                          <option key={String(option.value)} value={String(option.value)}>
                            {option.label}
                          </option>
                        ))}
                      </select>
                    )}
                    {filter.kind === 'text' && (
                      <input id={id} type="text" value={typeof value === 'string' ? value : ''} onChange={(event) => set(filter.name, event.target.value || null)} className={controlClass} />
                    )}
                    {filter.kind === 'daterange' && (
                      <div className="flex items-center gap-2">
                        {(['start', 'end'] as const).map((edge) => (
                          <input
                            key={edge}
                            id={edge === 'start' ? id : undefined}
                            type="date"
                            aria-label={`${filter.label}: ${t(edge === 'start' ? 'from' : 'to')}`}
                            value={typeof value === 'object' && value ? (value[edge] ?? '') : ''}
                            onChange={(event) => {
                              const range = typeof value === 'object' && value ? value : { start: null, end: null };
                              const next = { ...range, [edge]: event.target.value || null };
                              set(filter.name, next.start || next.end ? next : null);
                            }}
                            className={controlClass}
                          />
                        ))}
                      </div>
                    )}
                  </div>
                );
              })}
              <div className="flex justify-between gap-2 pt-1">
                <button
                  type="button"
                  className={buttonClass('ghost', 'sm')}
                  onClick={() => {
                    close();
                    visitWith({}, {});
                  }}
                >
                  {t('reset')}
                </button>
                <button type="submit" className={buttonClass('primary', 'sm')}>
                  {t('apply')}
                </button>
              </div>
            </form>
          )}
        </Dialog>
      </Popover>
    </DialogTrigger>
  );
}

