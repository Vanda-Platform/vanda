import { router } from '@inertiajs/react';
import { useState, type MouseEvent } from 'react';
import { Dialog, Heading, Modal, ModalOverlay } from 'react-aria-components';
import { t } from '../i18n';
import { walk } from '../patch';
import { isInternal, nodeAttributes, useSnapshot, useStore } from '../store';
import { Avatar, buttonClass, cx, Icon } from '../ui';
import type { UiNode } from '../types';
import type { ButtonNode } from './actions';
import { CopyButton } from '../copy';
import { BlockTitleContext } from './blockTitle';
import { NodeList, NodeView } from './NodeView';

const COLUMNS: Record<number, string> = {
  1: 'grid-cols-1',
  2: 'grid-cols-1 md:grid-cols-2',
  3: 'grid-cols-1 md:grid-cols-3',
  4: 'grid-cols-1 md:grid-cols-2 xl:grid-cols-4',
};

/**
 * A titled block of a form. Blocks on a page sit 16px apart (cards, charts, alerts);
 * a section adds 24px above itself, so sections are 40px apart, as in the design.
 * Its width stays within the design's 1120px page, while tables and charts use the full width.
 */
export function SectionView({ node }: { node: UiNode & { title: string; description?: string; commandIds?: string[] } }) {
  const commands = new Set(node.commandIds ?? []);
  const content = (node.children ?? []).filter((child) => !commands.has(child.id));
  const buttons = (node.children ?? []).filter((child) => commands.has(child.id));

  // The title and description sit above the card with the fields, the card takes the full width.
  return (
    <section className="flex min-w-0 flex-col gap-3 not-first:mt-4" {...nodeAttributes(node)}>
      <div className="flex max-w-[720px] flex-col gap-0.5">
        <h2 className="text-[15px] leading-[22px] font-semibold">{node.title}</h2>
        {node.description && <p className="text-[13.5px] text-fg-2">{node.description}</p>}
      </div>
      <div className="min-w-0 rounded-xl border border-line bg-surface shadow-sm">
        <div className="flex flex-col gap-5 p-4 [--v-gutter:16px] md:p-6 md:[--v-gutter:24px]">
          <BlockTitleContext.Provider value={node.title}>
            <NodeList nodes={content} />
          </BlockTitleContext.Provider>
        </div>
        {buttons.length > 0 && (
          <div className="flex flex-wrap justify-end gap-2 border-t border-line px-4 py-3 md:px-6">
            <NodeList nodes={buttons} />
          </div>
        )}
      </div>
    </section>
  );
}

/**
 * Layout::card(): title on top, buttons in the header, content below, full width.
 * Tables inside bleed to the card's edges (24px gutter).
 */
export function CardView({ node }: { node: UiNode & { title: string; description?: string; commandIds?: string[] } }) {
  const commands = new Set(node.commandIds ?? []);
  const content = (node.children ?? []).filter((child) => !commands.has(child.id));
  const buttons = (node.children ?? []).filter((child) => commands.has(child.id));

  return (
    <section className="flex min-w-0 flex-col rounded-xl border border-line bg-surface shadow-sm" {...nodeAttributes(node)}>
      <header className="flex flex-wrap items-start justify-between gap-3 px-4 pt-4 md:px-6 md:pt-5">
        <div className="flex min-w-0 flex-col gap-0.5">
          <h2 className="text-[15px] leading-[22px] font-semibold">{node.title}</h2>
          {node.description && <p className="text-[13.5px] text-fg-2">{node.description}</p>}
        </div>
        {buttons.length > 0 && (
          <div className="flex flex-wrap gap-2">
            {buttons.map((child) => (
              <NodeView key={child.id} node={child} compact />
            ))}
          </div>
        )}
      </header>
      <div className="flex flex-col gap-5 p-4 [--v-gutter:16px] md:p-6 md:pt-5 md:[--v-gutter:24px]">
        <BlockTitleContext.Provider value={node.title}>
          <NodeList nodes={content} />
        </BlockTitleContext.Provider>
      </div>
    </section>
  );
}

export function GridView({ node }: { node: UiNode & { columns?: number } }) {
  return (
    // *:mt-0!: blocks side by side start at the same height (a section adds space above itself in a column).
    <div className={cx('grid gap-5 *:mt-0!', COLUMNS[node.columns ?? 1] ?? COLUMNS[2])} {...nodeAttributes(node)}>
      <NodeList nodes={node.children ?? []} />
    </div>
  );
}

/** Tabs switch on the client and remember the active one in ?tab=…; a tab with errors is marked. */
/** Nodes that are a block of their own on a page; fields and other content around them get a card. */
const BLOCKS = new Set(['section', 'card', 'table', 'treetable', 'tabs', 'metrics', 'chart', 'alert', 'empty', 'actionlist']);

const isBlock = (node: UiNode): boolean =>
  BLOCKS.has(node.type) || (node.type === 'grid' && (node.children ?? []).length > 0 && (node.children ?? []).every(isBlock));

/**
 * The content of a page or a tab. Sections, cards and tables stand on their own; fields placed
 * straight on the page are gathered into a card, so every field sits one level deep (one border
 * per level), as in a section.
 */
export function BlockList({ nodes }: { nodes: UiNode[] }) {
  const runs: UiNode[][] = [];
  for (const child of nodes) {
    const last = runs[runs.length - 1];
    if (last && !isBlock(child) && !isBlock(last[0]!)) last.push(child);
    else runs.push([child]);
  }

  return runs.map((run) =>
    isBlock(run[0]!) ? (
      <NodeView key={run[0]!.id} node={run[0]!} />
    ) : (
      <div key={`${run[0]!.id}-card`} className="flex min-w-0 flex-col gap-5 rounded-xl border border-line bg-surface p-4 shadow-sm [--v-gutter:16px] md:p-6 md:[--v-gutter:24px]">
        <NodeList nodes={run} />
      </div>
    ),
  );
}

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
        <div key={tab.id} role="tabpanel" id={`${tab.id}-panel`} aria-labelledby={`${tab.id}-tab`} hidden={index !== active} className="flex flex-col gap-4">
          <BlockList nodes={tab.children ?? []} />
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
    // On phones a modal is a sheet from the bottom, scrolling inside, its buttons pinned to the bottom.
    <ModalOverlay
      isOpen={open}
      onOpenChange={(next) => store.openModal(next ? node.id : null)}
      isDismissable
      className="fixed inset-0 z-40 grid overflow-y-auto bg-(--v-scrim) max-md:items-end md:place-items-center md:p-4"
    >
      <Modal
        className={cx(
          'w-full border border-line bg-surface shadow-pop outline-none md:rounded-xl',
          'max-md:max-h-[92dvh] max-md:overflow-y-auto max-md:overscroll-contain max-md:rounded-t-2xl max-md:border-b-0 max-md:data-[entering]:animate-[vanda-sheet-in_220ms_cubic-bezier(0.2,0.8,0.2,1)] max-md:motion-reduce:animate-none',
          node.size === 'lg' ? 'md:max-w-2xl' : 'md:max-w-[440px]',
        )}
      >
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
              <div className="flex flex-col gap-3.5 px-5 py-4">
                <NodeList nodes={content} />
              </div>
              <div className="flex justify-end gap-2 border-t border-line bg-surface px-5 pt-3 pb-4 max-md:sticky max-md:bottom-0 max-md:pb-[calc(16px+env(safe-area-inset-bottom))]">
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

export function PersonaView({ node }: { node: UiNode & { name: string; subtitle?: string; initials: string; hue?: number; image?: string; href?: string } }) {
  const name = node.href ? (
    <a href={node.href} className="truncate font-medium text-fg underline-offset-4 hover:underline">
      {node.name}
    </a>
  ) : (
    <span className="truncate font-medium">{node.name}</span>
  );

  return (
    <div className="flex min-w-0 items-center gap-3" {...nodeAttributes(node)}>
      <Avatar initials={node.initials} hue={node.hue} image={node.image} />
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
            {item.copyable && item.value.type === 'text' && <CopyButton text={String(item.value.text)} />}
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
    // Two in a row on phones, so four numbers fit on one screen.
    <div className="grid grid-cols-2 gap-3 md:grid-cols-[repeat(auto-fit,minmax(220px,1fr))] md:gap-4" {...nodeAttributes(node)}>
      {(node.items ?? []).map((metric) => (
        <div key={metric.label} className="flex min-w-0 flex-col gap-2 rounded-xl border border-line bg-surface p-3.5 shadow-sm md:p-[18px] md:pb-3.5">
          <span className="text-[13px] font-medium text-fg-3 md:text-[13.5px]">{metric.label}</span>
          <div className="flex flex-wrap items-baseline gap-x-2.5 gap-y-1">
            <span className="text-[20px] leading-7 font-semibold tracking-[-0.02em] tabular-nums md:text-[26px] md:leading-8">{metric.value}</span>
            {metric.delta && (
              <span className={cx('inline-flex min-h-[22px] items-center gap-1 rounded-[11px] px-2 py-0.5 text-[12px] leading-[1.3] font-medium', metric.good ? TONES.success : TONES.danger)}>
                <Icon name={metric.good ? 'arrow-up' : 'arrow-down'} className="size-3 shrink-0" />
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

/** Nothing here yet: an icon, a sentence and a way to start. */
export function EmptyStateView({ node }: { node: UiNode & { title: string; description?: string; icon?: string } }) {
  return (
    <div className="flex flex-col items-center gap-2.5 rounded-xl bg-surface-2 px-4 py-7 text-center" {...nodeAttributes(node)}>
      <span aria-hidden="true" className="grid size-11 place-items-center rounded-xl border border-line bg-surface text-fg-3">
        <Icon name={node.icon ?? 'file'} className="size-5" />
      </span>
      <h3 className="text-[15px] font-semibold">{node.title}</h3>
      {node.description && <p className="max-w-[280px] text-[13.5px] text-fg-2">{node.description}</p>}
      {(node.children ?? []).length > 0 && (
        <div className="mt-1 flex flex-wrap justify-center gap-2">
          {(node.children ?? []).map((child) => (
            <NodeView key={child.id} node={child} compact />
          ))}
        </div>
      )}
    </div>
  );
}

/** Layout::actions(): rows edge to edge in a section card, separated by rules. */
export function ActionListView({ node }: { node: UiNode }) {
  return (
    <div className="-m-6 flex flex-col divide-y divide-line" {...nodeAttributes(node)}>
      <NodeList nodes={node.children ?? []} />
    </div>
  );
}

export function ActionRowView({ node }: { node: UiNode & { title: string; description?: string } }) {
  return (
    <div className="flex flex-wrap items-center justify-between gap-4 px-6 py-[18px]" {...nodeAttributes(node)}>
      <div className="flex min-w-0 flex-col gap-0.5">
        <span className="font-medium">{node.title}</span>
        {node.description && <span className="text-[12.5px] text-fg-3">{node.description}</span>}
      </div>
      <div className="flex flex-wrap gap-2">
        <NodeList nodes={node.children ?? []} />
      </div>
    </div>
  );
}

/**
 * Layout::view(): HTML a Blade view rendered on the server. It is set as markup, so its
 * scripts do not run; links to panel pages open through Inertia like any other link.
 */
export function ViewIsland({ node }: { node: UiNode & { html: string; plain?: boolean } }) {
  const onClick = (event: MouseEvent<HTMLDivElement>) => {
    const link = (event.target as HTMLElement).closest('a');
    const href = link?.getAttribute('href') ?? undefined;
    if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
    if (link.target && link.target !== '_self') return;
    if (link.hasAttribute('download') || !isInternal(href) || href.startsWith('#')) return;
    event.preventDefault();
    router.visit(href);
  };

  return <div className={cx('min-w-0', !node.plain && 'vanda-prose')} onClick={onClick} dangerouslySetInnerHTML={{ __html: node.html }} {...nodeAttributes(node)} />;
}
