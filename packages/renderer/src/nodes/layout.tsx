import { nodeAttributes } from '../store';
import { t } from '../i18n';
import { cx } from '../ui';
import type { UiNode } from '../types';
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

interface Column {
  name: string;
  label: string;
  align?: 'right' | 'center';
  width?: string;
  muted?: boolean;
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

export function TableView({ node }: { node: UiNode & { columns?: Column[]; rows?: Row[]; pagination?: Pagination; emptyText?: string } }) {
  const columns = node.columns ?? [];
  const rows = node.rows ?? [];
  const pagination = node.pagination;
  const pageUrl = (page: number) => {
    const url = new URL(window.location.href);
    url.searchParams.set('page', String(page));
    return url.toString();
  };

  return (
    <div className="-mx-8 flex flex-col" {...nodeAttributes(node)}>
      <div className="overflow-x-auto">
        <table className="w-full border-separate border-spacing-0 text-[14px]">
          <thead>
            <tr>
              {columns.map((column) => (
                <th
                  key={column.name}
                  scope="col"
                  style={{ width: column.width }}
                  className={cx(
                    'px-3 py-2.5 text-[12.5px] font-medium whitespace-nowrap text-fg-3 first:pl-8 last:pr-8',
                    column.align === 'right' ? 'text-right' : column.align === 'center' ? 'text-center' : 'text-left',
                  )}
                >
                  {column.label}
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {rows.map((row) => (
              <tr
                key={row.key}
                className={cx('group', row.link && 'cursor-pointer')}
                onClick={(event) => {
                  if (row.link && !(event.target as HTMLElement).closest('a,button')) window.location.assign(row.link);
                }}
              >
                {columns.map((column, index) => {
                  const cell = row.cells[column.name];
                  return (
                    <td
                      key={column.name}
                      className={cx(
                        'border-t border-line px-3 py-3 align-middle whitespace-nowrap first:pl-8 last:pr-8 group-hover:bg-surface-2/60',
                        column.muted && 'text-fg-3',
                        column.align === 'right' ? 'text-right' : column.align === 'center' ? 'text-center' : 'text-left',
                      )}
                    >
                      {cell && index === 0 && row.link && cell.type === 'text' ? (
                        <a href={row.link} className="font-medium text-fg no-underline hover:underline underline-offset-4">
                          {String(cell.text)}
                        </a>
                      ) : cell ? (
                        <NodeView node={cell} compact />
                      ) : null}
                    </td>
                  );
                })}
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {rows.length === 0 && <p className="border-t border-line px-8 py-12 text-center text-fg-3">{node.emptyText ?? t('empty')}</p>}

      {pagination && pagination.lastPage > 1 && (
        <nav aria-label="Pagination" className="flex items-center justify-between gap-3 border-t border-line px-8 py-3.5 text-[13.5px] text-fg-3">
          <span className="tabular-nums">{t('shown', { from: pagination.from ?? 0, to: pagination.to ?? 0, total: pagination.total })}</span>
          <span className="flex gap-1">
            {pagination.page > 1 && (
              <a href={pageUrl(pagination.page - 1)} aria-label={t('previous')} className="rounded-md px-2 py-1 hover:bg-surface-2">
                ←
              </a>
            )}
            <span className="rounded-md border border-line-strong px-2 py-1 text-fg tabular-nums">{pagination.page}</span>
            {pagination.page < pagination.lastPage && (
              <a href={pageUrl(pagination.page + 1)} aria-label={t('next')} className="rounded-md px-2 py-1 hover:bg-surface-2">
                →
              </a>
            )}
          </span>
        </nav>
      )}
    </div>
  );
}
