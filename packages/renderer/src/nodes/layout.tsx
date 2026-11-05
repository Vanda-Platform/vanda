import { router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { Dialog, Heading, Modal, ModalOverlay } from 'react-aria-components';
import { t } from '../i18n';
import { walk } from '../patch';
import { isInternal, nodeAttributes, useSnapshot, useStore } from '../store';
import { Avatar, buttonClass, controlClass, cx, Icon } from '../ui';
import type { UiNode } from '../types';
import { ButtonView, type ButtonNode } from './actions';
import { NodeList, NodeView } from './NodeView';

const COLUMNS: Record<number, string> = {
  1: 'grid-cols-1',
  2: 'grid-cols-1 md:grid-cols-2',
  3: 'grid-cols-1 md:grid-cols-3',
  4: 'grid-cols-1 md:grid-cols-2 xl:grid-cols-4',
};

export function SectionView({ node }: { node: UiNode & { title: string; description?: string; commandIds?: string[] } }) {
  const commands = new Set(node.commandIds ?? []);
  const content = (node.children ?? []).filter((child) => !commands.has(child.id));
  const buttons = (node.children ?? []).filter((child) => commands.has(child.id));

  return (
    <section className="flex flex-wrap gap-6 border-b border-line pb-10 last:border-b-0 last:pb-0" {...nodeAttributes(node)}>
      <div className="flex max-w-[300px] flex-[1_1_220px] flex-col gap-1.5">
        <h2 className="text-[15px] leading-[22px] font-semibold">{node.title}</h2>
        {node.description && <p className="text-[13.5px] text-fg-2">{node.description}</p>}
      </div>
      <div className="min-w-0 flex-[999_1_480px] rounded-xl border border-line bg-surface shadow-sm">
        <div className="flex flex-col gap-5 p-6">
          <NodeList nodes={content} />
        </div>
        {buttons.length > 0 && (
          <div className="flex justify-end gap-2 border-t border-line px-6 py-3">
            <NodeList nodes={buttons} />
          </div>
        )}
      </div>
    </section>
  );
}

export function GridView({ node }: { node: UiNode & { columns?: number } }) {
  return (
    <div className={cx('grid gap-5', COLUMNS[node.columns ?? 1] ?? COLUMNS[2])} {...nodeAttributes(node)}>
      <NodeList nodes={node.children ?? []} />
    </div>
  );
}

/** Tabs switch on the client and remember the active one in ?tab=…; a tab with errors is marked. */
export function TabsView({ node }: { node: UiNode }) {
  const tabs = node.children ?? [];
  const errors = useSnapshot((s) => s.errors);
  const [active, setActive] = useState(() => {
    const fromUrl = Number(new URLSearchParams(window.location.search).get('tab'));
    return Number.isInteger(fromUrl) && fromUrl >= 0 && fromUrl < tabs.length ? fromUrl : 0;
  });

  const select = (index: number) => {
    setActive(index);
    const url = new URL(window.location.href);
    url.searchParams.set('tab', String(index));
    window.history.replaceState(window.history.state, '', url);
  };

  const hasErrors = (tab: UiNode) =>
    walk(tab.children ?? []).some((child) => typeof child.path === 'string' && Object.keys(errors).some((key) => key === child.path || key.startsWith(`${child.path}.`)));

  return (
    <div className="flex flex-col gap-6" {...nodeAttributes(node)}>
      <div role="tablist" className="flex gap-6 overflow-x-auto border-b border-line">
        {tabs.map((tab, index) => (
          <button
            key={tab.id}
            type="button"
            role="tab"
            id={`${tab.id}-tab`}
            aria-selected={index === active}
            aria-controls={`${tab.id}-panel`}
            onClick={() => select(index)}
            className={cx(
              'relative flex items-center gap-2 py-2.5 text-[14px] font-medium whitespace-nowrap text-fg-2 hover:text-fg',
              index === active && 'text-fg shadow-[inset_0_-2px_0_var(--v-accent)]',
            )}
          >
            {String(tab.title)}
            {hasErrors(tab) && <span aria-label={t('hasErrors')} className="size-1.5 rounded-full bg-danger" />}
          </button>
        ))}
      </div>
      {tabs.map((tab, index) => (
        <div key={tab.id} role="tabpanel" id={`${tab.id}-panel`} aria-labelledby={`${tab.id}-tab`} hidden={index !== active} className="flex flex-col gap-6">
          <NodeList nodes={tab.children ?? []} />
        </div>
      ))}
    </div>
  );
}

export function ModalView({ node }: { node: UiNode & { title: string; description?: string; size?: string; buttonIds?: string[] } }) {
  const store = useStore();
  const open = useSnapshot((s) => s.modal === node.id);
  const buttons = new Set(node.buttonIds ?? []);
  const content = (node.children ?? []).filter((child) => !buttons.has(child.id));
  const footer = (node.children ?? []).filter((child) => buttons.has(child.id));

  return (
    <ModalOverlay isOpen={open} onOpenChange={(next) => store.openModal(next ? node.id : null)} isDismissable className="fixed inset-0 z-40 grid place-items-center overflow-y-auto bg-(--v-scrim) p-4">
      <Modal className={cx('w-full rounded-2xl border border-line bg-surface shadow-pop outline-none', node.size === 'lg' ? 'max-w-2xl' : 'max-w-md')}>
        <Dialog className="flex flex-col outline-none" {...nodeAttributes(node)}>
          {({ close }) => (
            <form
              onSubmit={(event) => {
                event.preventDefault();
                const primary = footer.find((child) => child.variant === 'primary') as ButtonNode | undefined;
                if (primary?.action) void store.runAction(primary.id, primary.action);
              }}
            >
              <div className="flex items-start justify-between gap-3 px-5 pt-[18px] pb-1">
                <div className="flex flex-col gap-0.5">
                  <Heading slot="title" className="text-[16px] font-semibold">
                    {node.title}
                  </Heading>
                  {node.description && <p className="text-[13.5px] text-fg-2">{node.description}</p>}
                </div>
                <button type="button" onClick={close} aria-label={t('close')} className="grid size-7 place-items-center rounded-md text-fg-3 hover:bg-surface-2 hover:text-fg">
                  <Icon name="x" />
                </button>
              </div>
              <div className="flex flex-col gap-4 px-5 py-4">
                <NodeList nodes={content} />
              </div>
              <div className="flex justify-end gap-2 border-t border-line px-5 py-3">
                <button type="button" onClick={close} className={buttonClass('secondary')}>
                  {t('cancel')}
                </button>
                <NodeList nodes={footer} />
              </div>
              <button type="submit" tabIndex={-1} aria-hidden="true" className="sr-only" />
            </form>
          )}
        </Dialog>
      </Modal>
    </ModalOverlay>
  );
}

export function TextView({ node }: { node: UiNode & { text: string; muted?: boolean; mono?: boolean } }) {
  return (
    <span className={cx(node.muted && 'text-fg-3', node.mono && 'font-mono text-[12.5px]')} {...nodeAttributes(node)}>
      {node.text}
    </span>
  );
}

const TONES: Record<string, string> = {
  neutral: 'bg-surface-2 text-fg-2 border-line',
  accent: 'bg-accent-soft text-accent-fg border-transparent',
  success: 'bg-success-soft text-success-fg border-transparent',
  warning: 'bg-warning-soft text-warning-fg border-transparent',
  danger: 'bg-danger-soft text-danger-fg border-transparent',
};

export function BadgeView({ node }: { node: UiNode & { label: string; tone?: string; dot?: boolean } }) {
  return (
    <span
      className={cx('inline-flex h-[22px] items-center gap-1.5 rounded-full border px-2 text-[12px] font-medium whitespace-nowrap', TONES[node.tone ?? 'neutral'])}
      {...nodeAttributes(node)}
    >
      {node.dot && <span aria-hidden="true" className="size-1.5 rounded-full bg-current" />}
      {node.label}
    </span>
  );
}

const ALERT_ICONS: Record<string, string> = { accent: 'info', success: 'success', warning: 'warning', danger: 'alert', neutral: 'info' };

export function AlertView({ node }: { node: UiNode & { text: string; title?: string; tone?: string } }) {
  const tone = node.tone ?? 'accent';
  return (
    <div role={tone === 'danger' ? 'alert' : 'status'} className={cx('flex items-start gap-2.5 rounded-lg px-3.5 py-3 text-[14px]', TONES[tone])} {...nodeAttributes(node)}>
      <Icon name={ALERT_ICONS[tone]} className="mt-0.5 size-4 shrink-0" />
      <div>
        {node.title && <strong className="font-semibold">{node.title} </strong>}
        {node.text}
      </div>
    </div>
  );
}

export function PersonaView({ node }: { node: UiNode & { name: string; subtitle?: string; initials: string; hue?: number; href?: string } }) {
  const name = node.href ? (
    <a href={node.href} className="font-medium text-fg underline-offset-4 hover:underline">
      {node.name}
    </a>
  ) : (
    <span className="font-medium">{node.name}</span>
  );

  return (
    <div className="flex min-w-0 items-center gap-3" {...nodeAttributes(node)}>
      <Avatar initials={node.initials} hue={node.hue} />
      <div className="flex min-w-0 flex-col leading-tight">
        {name}
        {node.subtitle && <span className="truncate text-[13px] text-fg-3">{node.subtitle}</span>}
      </div>
    </div>
  );
}

export function LegendView({ node }: { node: UiNode & { items?: { label: string; value: UiNode; copyable?: boolean }[] } }) {
  return (
    <dl className="grid grid-cols-[minmax(120px,180px)_minmax(0,1fr)] gap-x-6 gap-y-3 text-[14px]" {...nodeAttributes(node)}>
      {(node.items ?? []).map((item) => (
        <div key={item.label} className="contents">
          <dt className="text-fg-3">{item.label}</dt>
          <dd className="m-0 flex min-w-0 items-center gap-1.5">
            <NodeView node={item.value} compact />
            {item.copyable && item.value.type === 'text' && (
              <button
                type="button"
                aria-label={t('copy')}
                onClick={() => void navigator.clipboard.writeText(String(item.value.text))}
                className="grid size-6 place-items-center rounded-md text-fg-3 hover:bg-surface-2 hover:text-fg"
              >
                <Icon name="copy" className="size-3.5" />
              </button>
            )}
          </dd>
        </div>
      ))}
    </dl>
  );
}

interface MetricItem {
  label: string;
  value: string;
  delta?: string;
  good?: boolean;
  trend?: number[];
  hint?: string;
}

function Sparkline({ values, good }: { values: number[]; good: boolean }) {
  const min = Math.min(...values);
  const max = Math.max(...values);
  const points = values.map((value, index) => `${(index / Math.max(values.length - 1, 1)) * 100},${30 - ((value - min) / (max - min || 1)) * 26}`).join(' ');

  return (
    <svg viewBox="0 0 100 32" preserveAspectRatio="none" aria-hidden="true" className="h-9 w-full overflow-visible">
      <polyline
        points={points}
        vectorEffect="non-scaling-stroke"
        style={{ fill: 'none', stroke: good ? 'var(--v-accent)' : 'var(--v-danger)', strokeWidth: 1.75, strokeLinecap: 'round', strokeLinejoin: 'round' }}
      />
    </svg>
  );
}

export function MetricsView({ node }: { node: UiNode & { items?: MetricItem[] } }) {
  return (
    <div className="grid grid-cols-[repeat(auto-fit,minmax(220px,1fr))] gap-4" {...nodeAttributes(node)}>
      {(node.items ?? []).map((metric) => (
        <div key={metric.label} className="flex flex-col gap-2 rounded-xl border border-line bg-surface p-[18px] pb-3.5 shadow-sm">
          <span className="text-[13.5px] font-medium text-fg-3">{metric.label}</span>
          <div className="flex flex-wrap items-baseline gap-2.5">
            <span className="text-[26px] leading-8 font-semibold tracking-[-0.02em] tabular-nums">{metric.value}</span>
            {metric.delta && (
              <span className={cx('inline-flex h-[22px] items-center gap-1 rounded-full px-2 text-[12px] font-medium', metric.good ? TONES.success : TONES.danger)}>
                <Icon name={metric.good ? 'arrow-up' : 'arrow-down'} className="size-3" />
                {metric.delta}
              </span>
            )}
          </div>
          {metric.trend && metric.trend.length > 1 && <Sparkline values={metric.trend} good={metric.good ?? true} />}
          {metric.hint && <span className="text-[12.5px] text-fg-3">{metric.hint}</span>}
        </div>
      ))}
    </div>
  );
}

interface Column {
  name: string;
  label: string;
  align?: 'right' | 'center';
  width?: string;
  muted?: boolean;
  sortable?: boolean;
}

interface Row {
  key: string;
  link?: string;
  cells: Record<string, UiNode>;
}

interface Pagination {
  page: number;
  lastPage: number;
  total: number;
  from: number | null;
  to: number | null;
}

/** Visit the same screen with changed query parameters, keeping scroll and local state. */
function visitWith(params: Record<string, string | null>) {
  const url = new URL(window.location.href);
  for (const [key, value] of Object.entries(params)) {
    if (value === null || value === '') url.searchParams.delete(key);
    else url.searchParams.set(key, value);
  }
  router.visit(url.toString(), { preserveScroll: true, preserveState: true, replace: true });
}

export function TableView({
  node,
}: {
  node: UiNode & { columns?: Column[]; rows?: Row[]; pagination?: Pagination; emptyText?: string; sort?: string; search?: { value: string; placeholder?: string }; bulk?: ButtonNode[] };
}) {
  const columns = node.columns ?? [];
  const rows = node.rows ?? [];
  const bulk = node.bulk ?? [];
  const pagination = node.pagination;
  const [selected, setSelected] = useState<string[]>([]);
  const [query, setQuery] = useState(node.search?.value ?? '');
  const debounce = useRef<ReturnType<typeof setTimeout>>(undefined);

  // A new page of rows (search, sort, after an action) drops selections that are gone.
  useEffect(() => setSelected((current) => current.filter((key) => rows.some((row) => row.key === key))), [rows]);

  const allChecked = rows.length > 0 && rows.every((row) => selected.includes(row.key));
  const toggle = (key: string) => setSelected((current) => (current.includes(key) ? current.filter((k) => k !== key) : [...current, key]));

  const sortBy = (column: string) => {
    const next = node.sort === column ? `-${column}` : node.sort === `-${column}` ? null : column;
    visitWith({ sort: next, page: null });
  };

  return (
    <div className="-mx-8 flex flex-col" {...nodeAttributes(node)}>
      {node.search && selected.length === 0 && (
        <div className="flex flex-wrap items-center gap-2 px-8 pb-3.5">
          <div className="relative flex w-[300px] max-w-full items-center">
            <Icon name="search" className="pointer-events-none absolute left-2.5 size-4 text-fg-3" />
            <input
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
              className={cx(controlClass, 'pl-8')}
            />
          </div>
        </div>
      )}

      {selected.length > 0 && (
        <div className="mx-5 mb-2.5 flex flex-wrap items-center gap-2 rounded-[10px] bg-accent-soft py-1.5 pr-1.5 pl-3.5 text-accent-fg">
          <span className="text-[14px] font-semibold">{t('selected', { count: selected.length })}</span>
          <div className="flex-grow" />
          {bulk.map((button) => (
            <ButtonView key={button.id} node={{ ...button, variant: button.variant === 'danger' ? 'danger' : 'secondary' }} size="sm" ids={selected} />
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
                <th className="w-11 pl-8">
                  <input
                    type="checkbox"
                    aria-label={t('selectAll')}
                    checked={allChecked}
                    onChange={() => setSelected(allChecked ? [] : rows.map((row) => row.key))}
                    className="size-4 accent-(--v-accent)"
                  />
                </th>
              )}
              {columns.map((column) => {
                const direction = node.sort === column.name ? 'asc' : node.sort === `-${column.name}` ? 'desc' : null;
                return (
                  <th
                    key={column.name}
                    scope="col"
                    style={{ width: column.width }}
                    aria-sort={direction === 'asc' ? 'ascending' : direction === 'desc' ? 'descending' : undefined}
                    className={cx(
                      'px-3 py-2.5 text-[12.5px] font-medium whitespace-nowrap text-fg-3 first:pl-8 last:pr-8',
                      column.align === 'right' ? 'text-right' : column.align === 'center' ? 'text-center' : 'text-left',
                    )}
                  >
                    {column.sortable ? (
                      <button type="button" onClick={() => sortBy(column.name)} className={cx('inline-flex items-center gap-1 hover:text-fg', direction && 'text-fg')}>
                        {column.label}
                        <Icon name={direction === 'asc' ? 'arrow-up' : direction === 'desc' ? 'arrow-down' : 'sort'} className={cx('size-3.5', !direction && 'opacity-40')} />
                      </button>
                    ) : (
                      column.label
                    )}
                  </th>
                );
              })}
            </tr>
          </thead>
          <tbody>
            {rows.map((row) => {
              const isSelected = selected.includes(row.key);
              return (
                <tr
                  key={row.key}
                  className={cx('group', row.link && 'cursor-pointer')}
                  onClick={(event) => {
                    if (row.link && !(event.target as HTMLElement).closest('a,button,input')) {
                      if (isInternal(row.link)) router.visit(row.link);
                      else window.location.assign(row.link);
                    }
                  }}
                >
                  {bulk.length > 0 && (
                    <td className={cx('border-t border-line py-3 pl-8', isSelected ? 'bg-accent-soft' : 'group-hover:bg-surface-2/60')}>
                      <input type="checkbox" aria-label={t('selectRow')} checked={isSelected} onChange={() => toggle(row.key)} className="size-4 accent-(--v-accent)" />
                    </td>
                  )}
                  {columns.map((column, index) => {
                    const cell = row.cells[column.name];
                    return (
                      <td
                        key={column.name}
                        className={cx(
                          'border-t border-line px-3 py-3 align-middle whitespace-nowrap first:pl-8 last:pr-8',
                          isSelected ? 'bg-accent-soft' : 'group-hover:bg-surface-2/60',
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
                </tr>
              );
            })}
          </tbody>
        </table>
      </div>

      {rows.length === 0 && <p className="border-t border-line px-8 py-12 text-center text-fg-3">{node.emptyText ?? t('empty')}</p>}

      {pagination && pagination.lastPage > 1 && (
        <nav aria-label="Pagination" className="flex items-center justify-between gap-3 border-t border-line px-8 py-3.5 text-[13.5px] text-fg-3">
          <span className="tabular-nums">{t('shown', { from: pagination.from ?? 0, to: pagination.to ?? 0, total: pagination.total })}</span>
          <span className="flex items-center gap-1">
            <button
              type="button"
              aria-label={t('previous')}
              disabled={pagination.page <= 1}
              onClick={() => visitWith({ page: String(pagination.page - 1) })}
              className="grid size-7 place-items-center rounded-md hover:bg-surface-2 disabled:opacity-40"
            >
              <Icon name="chevron-left" />
            </button>
            <span className="grid h-7 min-w-7 place-items-center rounded-md border border-line-strong px-2 text-fg tabular-nums">{pagination.page}</span>
            <span className="tabular-nums">/ {pagination.lastPage}</span>
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
        </nav>
      )}
    </div>
  );
}
