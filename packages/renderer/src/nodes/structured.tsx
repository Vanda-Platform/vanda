import { useContext, useEffect, useId, useRef, useState, type CSSProperties, type KeyboardEvent, type PointerEvent } from 'react';
import { Button as AriaButton, Menu, MenuItem, MenuTrigger, Popover } from 'react-aria-components';
import { t } from '../i18n';
import { fullWidth, RowKeys, rowSummary } from '../repeaterRows';
import { nodeAttributes, useSnapshot, useStore, type Store } from '../store';
import { buttonClass, controlClass, cx, Icon, MOBILE_QUERY, readonlyClass, useMediaQuery } from '../ui';
import { BlockTitleContext } from './blockTitle';
import type { FieldNode, UiNode } from '../types';
import { repeaterHints } from '../fieldHelp';
import { FieldFrame, useField } from './fields';
import { HelpTip } from './help';
import { NodeView } from './NodeView';

type Row = Record<string, unknown>;

/** «Удалить строку 2 (Характеристики)»: a page may hold several lists, each with its row 2. */
const removeLabel = (index: number, list: string | undefined) => (list ? t('removeRowOf', { n: index + 1, label: list }) : t('removeRow', { n: index + 1 }));

/** Whether a row holds anything a user typed (an empty row goes without a word). */
const filled = (row: unknown): boolean =>
  row !== null && typeof row === 'object' && Object.values(row).some((value) => value !== null && value !== undefined && value !== '' && !(Array.isArray(value) && value.length === 0));

/**
 * Removes row `index` of the list at `path`; a filled one can come back with «Вернуть» in a toast
 * (one per list: the last row removed) until the page changes.
 */
function removeRow(store: Store, path: string, rows: unknown[], index: number) {
  store.setValue(path, rows.filter((_, i) => i !== index));
  if (!filled(rows[index])) return;
  store.notify(t('rowRemoved', { n: index + 1 }), { tone: 'neutral', key: `removed:${path}`, action: { label: t('restore'), run: () => store.setValue(path, rows) } });
}

const iconButton = 'grid size-7 shrink-0 place-items-center rounded-md text-fg-3 hover:bg-surface-2 hover:text-fg disabled:opacity-30';

type RepeaterNode = FieldNode & {
  fields?: FieldNode[];
  addLabel?: string;
  max?: number;
  /** Chosen on the server from the fields (Repeater::resolvedLayout()). */
  layout?: 'list' | 'table' | 'blocks';
  /** Blocks: "Вариант" gives "Вариант 1", "Вариант 2". */
  itemLabel?: string;
  /** Repeater::collapsed(): blocks open folded (but rows with errors and new ones). */
  collapsed?: boolean;
};

const same = (a?: string | null, b?: string | null) => Boolean(a && b && a.trim().toLowerCase() === b.trim().toLowerCase());

interface Drag {
  from: number;
  to: number;
  dy: number;
  /** How far the other rows move aside: the dragged row's height plus the gap. */
  step: number;
}

/** The element that scrolls the page's content: the card on a desktop, the window on a phone. */
function scrollerOf(element: HTMLElement): HTMLElement {
  for (let parent = element.parentElement; parent; parent = parent.parentElement) {
    const { overflowY } = getComputedStyle(parent);
    if ((overflowY === 'auto' || overflowY === 'scroll') && parent.scrollHeight > parent.clientHeight) return parent;
  }
  return document.scrollingElement as HTMLElement;
}

/**
 * Reordering by the grip: dragged with a mouse or a finger (pointer events), the content scrolling
 * when the row comes near its edge, or moved with the arrow keys while the grip has focus.
 */
function useRowDrag(count: number, move: (from: number, to: number) => void) {
  const rows = useRef<(HTMLElement | null)[]>([]);
  const current = useRef<Drag | null>(null);
  const [drag, setDrag] = useState<Drag | null>(null);

  const update = (next: Drag | null) => {
    current.current = next;
    setDrag(next);
  };

  const focusGrip = (index: number) => requestAnimationFrame(() => rows.current[index]?.querySelector<HTMLElement>('[data-grip]')?.focus());

  const gripProps = (index: number) => ({
    'data-grip': true,
    onPointerDown: (event: PointerEvent<HTMLButtonElement>) => {
      if (event.button !== 0) return;
      event.preventDefault();
      const grip = event.currentTarget;
      grip.setPointerCapture(event.pointerId);
      const startY = event.clientY;
      const scroller = scrollerOf(grip);
      const startScroll = scroller.scrollTop;
      const rects = rows.current.slice(0, count).map((row) => row?.getBoundingClientRect() ?? null);
      const own = rects[index];
      const next = rects[index + 1] ?? rects[index - 1];
      const gap = own && next ? Math.abs(next.top > own.top ? next.top - own.bottom : own.top - next.bottom) : 8;
      const step = (own?.height ?? 0) + gap;
      let pointerY = startY;
      let frame = 0;
      update({ from: index, to: index, dy: 0, step });

      // Positions are compared as they were when the drag began: what the content scrolled by is added back.
      const place = () => {
        const y = pointerY + scroller.scrollTop - startScroll;
        let to = index;
        rects.forEach((rect, i) => {
          if (!rect) return;
          const middle = rect.top + rect.height / 2;
          if (i < index && y < middle) to = Math.min(to, i);
          if (i > index && y > middle) to = Math.max(to, i);
        });
        update({ from: index, to, dy: y - startY, step });
      };
      // Near the top or the bottom edge of the scrolling content, it scrolls on its own, faster closer to the edge.
      const scroll = () => {
        const box = scroller === document.scrollingElement ? { top: 0, bottom: window.innerHeight } : scroller.getBoundingClientRect();
        const edge = 56;
        const speed = pointerY < box.top + edge ? -(box.top + edge - pointerY) : pointerY > box.bottom - edge ? pointerY - (box.bottom - edge) : 0;
        if (speed !== 0) {
          scroller.scrollTop += Math.max(-24, Math.min(24, speed / 3));
          place();
        }
        frame = requestAnimationFrame(scroll);
      };
      frame = requestAnimationFrame(scroll);

      const onMove = (moveEvent: globalThis.PointerEvent) => {
        pointerY = moveEvent.clientY;
        place();
      };
      const onUp = () => {
        cancelAnimationFrame(frame);
        grip.removeEventListener('pointermove', onMove);
        grip.removeEventListener('pointerup', onUp);
        grip.removeEventListener('pointercancel', onUp);
        const done = current.current;
        update(null);
        if (done && done.to !== done.from) {
          move(done.from, done.to);
          focusGrip(done.to);
        }
      };
      grip.addEventListener('pointermove', onMove);
      grip.addEventListener('pointerup', onUp);
      grip.addEventListener('pointercancel', onUp);
    },
    onKeyDown: (event: KeyboardEvent<HTMLButtonElement>) => {
      const to = event.key === 'ArrowUp' ? index - 1 : event.key === 'ArrowDown' ? index + 1 : null;
      if (to === null || to < 0 || to >= count) return;
      event.preventDefault();
      move(index, to);
      focusGrip(to);
    },
  });

  /** Where a row is drawn while another one is dragged. */
  const rowStyle = (index: number): CSSProperties | undefined => {
    if (!drag) return undefined;
    if (index === drag.from) return { transform: `translateY(${drag.dy}px)`, zIndex: 10, position: 'relative' };
    const shift = drag.from < drag.to && index > drag.from && index <= drag.to ? -drag.step : drag.to < drag.from && index >= drag.to && index < drag.from ? drag.step : 0;
    return { transform: `translateY(${shift}px)`, transition: 'transform 150ms ease' };
  };

  return { rows, drag, gripProps, rowStyle };
}

/** The first thing to type into in a row (not a hidden file input, not the grip or a menu). */
const FOCUSABLE = 'input:not([type=hidden]):not([type=file]):not([tabindex="-1"]), textarea:not([hidden]), select, [contenteditable="true"], [role="combobox"], [aria-haspopup="listbox"]';

/** A text input of a List row, where Enter adds the next row and Backspace in an empty one removes it. */
const typedInto = (target: EventTarget) =>
  target instanceof HTMLInputElement && ['text', 'tel', 'email', 'url', 'search', 'number'].includes(target.type) && target.getAttribute('role') !== 'combobox';

/**
 * Rows of the same fields; child paths are relative to the row ("form.specs.0.name").
 * One border per level: rows never get a box of their own inside the section's card.
 * List: one input per row (Enter adds the next). Table: columns with the labels once.
 * Blocks: groups divided by a line, each with a header that names it and folds it, and a menu
 * to duplicate, add below, move or remove it.
 */
export function RepeaterField({ node }: { node: RepeaterNode }) {
  const labelId = useId();
  const store = useStore();
  const field = useField(node);
  const mobile = useMediaQuery(MOBILE_QUERY);
  const blockTitle = useContext(BlockTitleContext);
  const rows = (Array.isArray(field.value) ? field.value : []) as Row[];
  const templates = node.fields ?? [];
  const canAdd = !field.disabled && (node.max === undefined || rows.length < node.max);
  // A table of inputs does not fit a phone: its rows become blocks there.
  const layout = node.layout === 'table' && mobile ? 'blocks' : (node.layout ?? 'blocks');
  const listName = node.label ?? blockTitle ?? undefined;

  const rowKeys = useRef(new RowKeys());
  const keys = rowKeys.current.list(rows.length);
  const [folded, setFolded] = useState<Set<string>>(() => new Set(node.collapsed ? keys : []));
  const [focusKey, setFocusKey] = useState<string | null>(null);
  const [said, setSaid] = useState('');

  // Rows with an error stay open: an error nobody sees is not fixed.
  const prefix = `${node.path}.`;
  const errorRows = useSnapshot((s) =>
    Object.keys(s.errors)
      .filter((key) => key.startsWith(prefix))
      .map((key) => key.slice(prefix.length).split('.')[0])
      .join(','),
  );
  const withErrors = new Set(errorRows.split(',').filter(Boolean).map(Number));

  // A new row (added, inserted, duplicated) takes the focus: the user goes on typing there.
  useEffect(() => {
    if (!focusKey) return;
    const row = document.querySelector<HTMLElement>(`[data-row-key="${focusKey}"][data-row-of="${CSS.escape(node.path)}"]`);
    const target = row?.querySelector<HTMLElement>(FOCUSABLE);
    target?.focus();
    row?.scrollIntoView({ block: 'nearest' });
    setFocusKey(null);
  }, [focusKey, node.path]);

  const set = (next: Row[]) => field.setValue(next);

  const move = (from: number, to: number) => {
    const next = [...rows];
    const [row] = next.splice(from, 1);
    next.splice(to, 0, row as Row);
    rowKeys.current.move(from, to);
    set(next);
    setSaid(t('rowMoved', { n: to + 1 }));
  };

  const insert = (at: number, row: Row = {}) => {
    const key = rowKeys.current.insert(at);
    set([...rows.slice(0, at), row, ...rows.slice(at)]);
    setFocusKey(key);
  };

  /** Removes a row; a filled one can come back with «Вернуть» in a toast, in its place and with its key. */
  const remove = (index: number, focusAfter = false) => {
    const before = rowKeys.current.snapshot();
    rowKeys.current.remove(index);
    set(rows.filter((_, i) => i !== index));
    if (focusAfter && rows.length > 1) setFocusKey(rowKeys.current.snapshot()[Math.max(0, index - 1)] ?? null);
    if (!filled(rows[index])) return;
    store.notify(t('rowRemoved', { n: index + 1 }), {
      tone: 'neutral',
      key: `removed:${node.path}`,
      action: {
        label: t('restore'),
        run: () => {
          rowKeys.current.restore(before);
          store.setValue(node.path, rows);
        },
      },
    });
  };

  const duplicate = (index: number) => insert(index + 1, structuredClone(rows[index] ?? {}));

  const { rows: rowRefs, drag, gripProps, rowStyle } = useRowDrag(rows.length, move);

  // A field whose label a header or the list stands for keeps its hint for screen readers only; the «i» is above.
  const child = (template: FieldNode, index: number, hideLabel: boolean) => {
    const path = `${node.path}.${index}.${template.path}`;
    return <NodeView key={template.path} node={{ ...template, id: path, path, labelHidden: hideLabel } as UiNode} />;
  };

  const rowName = (index: number) => (node.itemLabel ? `${node.itemLabel} ${index + 1}` : `№ ${index + 1}`);

  const grip = (index: number) =>
    !field.disabled && (
      <button
        type="button"
        {...gripProps(index)}
        aria-label={t('move', { label: layout === 'blocks' ? rowName(index) : String(index + 1) })}
        title={t('dragToMove')}
        className={cx(iconButton, 'size-9 cursor-grab touch-none active:cursor-grabbing md:size-8', drag?.from === index && 'text-fg')}
      >
        <Icon name="grip" />
      </button>
    );

  const removeButton = (index: number) =>
    !field.disabled && (
      <button type="button" className={cx(iconButton, 'size-9 hover:text-danger-fg md:size-8')} aria-label={removeLabel(index, listName)} title={t('remove')} onClick={() => remove(index)}>
        <Icon name="x" />
      </button>
    );

  // Hints are said once: the list's own and its single field's by its label, a column's in its header.
  const hints = repeaterHints(layout, node, templates);
  const tip = hints.label.length > 0 ? <HelpTip text={hints.label.join('\n')} label={listName} /> : null;
  const wide = fullWidth(templates.map((template) => template.type));
  const allFolded = rows.length > 0 && keys.every((key, index) => folded.has(key) && !withErrors.has(index));
  // Editors and maps that started in a folded block measure themselves again when it opens.
  const remeasure = () => requestAnimationFrame(() => window.dispatchEvent(new Event('resize')));
  const toggle = (key: string) => {
    if (folded.has(key)) remeasure();
    setFolded((current) => {
      const next = new Set(current);
      if (next.has(key)) next.delete(key);
      else next.add(key);
      return next;
    });
  };

  return (
    <div role="group" aria-labelledby={node.label ? labelId : undefined} className="flex flex-col gap-2" data-vanda-path={node.path} {...nodeAttributes(node)}>
      {(node.label || tip || (layout === 'blocks' && rows.length > 1)) && (
        <div className="flex min-h-6 items-center justify-between gap-2">
          {node.label ? (
            <span id={labelId} className={cx('flex items-center gap-1 text-[13px] font-medium', same(node.label, blockTitle) && 'sr-only')}>
              {node.label}
              {field.required && <span className="text-danger-fg" aria-hidden="true">*</span>}
            </span>
          ) : (
            <span />
          )}
          <span className="flex items-center gap-1">
            {layout === 'blocks' && rows.length > 1 && (
              <button
                type="button"
                className={cx(buttonClass('ghost', 'sm'), 'h-6 px-1.5 text-[12.5px] text-fg-3')}
                onClick={() => {
                  if (allFolded) remeasure();
                  setFolded(allFolded ? new Set() : new Set(keys));
                }}
              >
                {t(allFolded ? 'expandAll' : 'collapseAll')}
              </button>
            )}
            {tip}
          </span>
        </div>
      )}

      {/* The column names are for the eyes (every input has its own label); a column's «i» is for everyone. */}
      {layout === 'table' && rows.length > 0 && (
        <div className="flex items-center gap-1.5 text-[12.5px] font-medium text-fg-3">
          {!field.disabled && <span aria-hidden="true" className="w-9 shrink-0 md:w-8" />}
          {templates.map((template) => (
            <span key={template.path} className="flex min-w-0 flex-1 items-center gap-1">
              <span aria-hidden="true" className="truncate">
                {template.label ?? template.path}
                {template.required && <span className="ml-0.5 text-danger-fg">*</span>}
              </span>
              {hints.columns[template.path] && <HelpTip text={hints.columns[template.path]!} label={template.label ?? template.path} />}
            </span>
          ))}
          {!field.disabled && <span aria-hidden="true" className="w-9 shrink-0 md:w-8" />}
        </div>
      )}

      {rows.length > 0 && (
        <ol className={cx('flex flex-col', layout === 'blocks' ? '' : 'gap-2')}>
          {rows.map((row, index) => {
            const key = keys[index]!;
            const closed = layout === 'blocks' && folded.has(key) && !withErrors.has(index);
            const summary = layout === 'blocks' ? rowSummary(row, templates) : '';
            const bodyId = `${labelId}-${key}`;
            return (
              <li
                key={key}
                data-row-key={key}
                data-row-of={node.path}
                ref={(element) => {
                  rowRefs.current[index] = element;
                }}
                style={rowStyle(index)}
                onKeyDown={
                  layout === 'list' && !field.disabled
                    ? (event) => {
                        if (event.nativeEvent.isComposing || !typedInto(event.target)) return;
                        if (event.key === 'Enter' && !event.shiftKey && !event.metaKey && !event.ctrlKey && canAdd) {
                          event.preventDefault();
                          insert(index + 1);
                        } else if (event.key === 'Backspace' && (event.target as HTMLInputElement).value === '' && !filled(row) && rows.length > 1) {
                          event.preventDefault();
                          remove(index, true);
                        }
                      }
                    : undefined
                }
                className={cx(
                  'bg-surface',
                  layout === 'blocks' ? cx('flex flex-col', index > 0 && 'border-t border-line', closed ? 'py-1.5' : 'pt-1.5 pb-4', index === 0 && 'pt-0') : 'flex items-start gap-1.5',
                  drag?.from === index && 'rounded-lg shadow-pop',
                )}
              >
                {layout === 'blocks' ? (
                  <>
                    <div className="flex items-center gap-0.5">
                      {grip(index)}
                      <button
                        type="button"
                        aria-expanded={!closed}
                        aria-controls={bodyId}
                        onClick={() => toggle(key)}
                        className="group flex min-h-9 min-w-0 flex-1 items-center gap-2 rounded-md px-1.5 text-left text-[13.5px] outline-none hover:bg-surface-2/60 focus-visible:ring-2 focus-visible:ring-ring md:min-h-8"
                      >
                        <Icon name="chevron-down" className={cx('size-4 shrink-0 text-fg-3 transition-transform motion-reduce:transition-none', closed && '-rotate-90')} />
                        <span className={cx('shrink-0 tabular-nums', summary ? 'text-fg-3' : 'font-medium text-fg-2')}>{rowName(index)}</span>
                        {summary && <span className="min-w-0 truncate font-medium text-fg">{summary}</span>}
                        {withErrors.has(index) && (
                          <span className="ml-auto flex shrink-0 items-center gap-1 text-[12.5px] text-danger-fg">
                            <Icon name="alert" className="size-3.5" />
                            {t('rowHasErrors')}
                          </span>
                        )}
                      </button>
                      {!field.disabled && (
                        <RowMenu
                          name={summary ? `${rowName(index)} · ${summary}` : rowName(index)}
                          first={index === 0}
                          last={index === rows.length - 1}
                          canAdd={canAdd}
                          onDuplicate={() => duplicate(index)}
                          onAddBelow={() => insert(index + 1)}
                          onMove={(to) => move(index, to === 'up' ? index - 1 : index + 1)}
                        />
                      )}
                      {removeButton(index)}
                    </div>
                    {/* A folded block stays mounted (its editors keep their state), hidden from the eyes, Tab and screen readers. */}
                    <div id={bodyId} hidden={closed} className="@container pt-2 pl-0.5">
                      <div className="grid gap-x-3 gap-y-(--v-stack) @xl:grid-cols-2">
                        {templates.map((template, column) => (
                          <div key={template.path} className={cx('min-w-0', wide[column] && '@xl:col-span-2')}>
                            {child(template, index, false)}
                          </div>
                        ))}
                      </div>
                    </div>
                  </>
                ) : (
                  <>
                    {grip(index)}
                    {templates.map((template) => (
                      <div key={template.path} className="min-w-0 flex-1">
                        {child(template, index, true)}
                      </div>
                    ))}
                    {removeButton(index)}
                  </>
                )}
              </li>
            );
          })}
        </ol>
      )}

      {!field.disabled && (canAdd || node.max !== undefined) && (
        <div className={cx('flex items-center gap-3', layout === 'blocks' ? rows.length > 0 && 'border-t border-line pt-3' : rows.length > 0 && 'pl-[42px] md:pl-[38px]')}>
          {canAdd ? (
            <button type="button" className={cx(buttonClass('ghost', 'sm'), '-ml-2.5 text-fg-2')} onClick={() => insert(rows.length)}>
              <Icon name="plus" />
              {node.addLabel ?? t('add')}
            </button>
          ) : (
            <span className="text-[12.5px] text-fg-3">{t('rowsMax', { max: node.max ?? 0 })}</span>
          )}
          {canAdd && node.max !== undefined && rows.length > 0 && <span className="text-[12.5px] text-fg-3 tabular-nums">{t('rowsOf', { count: rows.length, max: node.max })}</span>}
        </div>
      )}

      {field.error ? (
        <span className="flex items-center gap-1.5 text-[12.5px] text-danger-fg">
          <Icon name="alert" className="size-3.5" />
          {field.error}
        </span>
      ) : (
        hints.inline.length > 0 && <span className={cx('text-[12.5px] text-fg-3', layout !== 'blocks' && rows.length > 0 && !field.disabled && 'pl-[42px] md:pl-[38px]')}>{hints.inline.join(' · ')}</span>
      )}

      <span className="sr-only" aria-live="polite">
        {said}
      </span>
    </div>
  );
}

/** «⋯» of a block: duplicate it, add an empty one below, move it a place up or down. */
function RowMenu({
  name,
  first,
  last,
  canAdd,
  onDuplicate,
  onAddBelow,
  onMove,
}: {
  name: string;
  first: boolean;
  last: boolean;
  canAdd: boolean;
  onDuplicate: () => void;
  onAddBelow: () => void;
  onMove: (direction: 'up' | 'down') => void;
}) {
  const items = [
    ...(canAdd ? [{ id: 'duplicate', label: t('duplicateRow'), icon: 'copy', run: onDuplicate }, { id: 'below', label: t('addBelow'), icon: 'plus', run: onAddBelow }] : []),
    ...(first ? [] : [{ id: 'up', label: t('moveUp'), icon: 'arrow-up', run: () => onMove('up') }]),
    ...(last ? [] : [{ id: 'down', label: t('moveDown'), icon: 'arrow-down', run: () => onMove('down') }]),
  ];
  if (items.length === 0) return null;

  return (
    <MenuTrigger>
      <AriaButton aria-label={t('actionsFor', { label: name })} className={cx(iconButton, 'size-9 md:size-8')}>
        <Icon name="more" />
      </AriaButton>
      <Popover placement="bottom end" className="min-w-52 rounded-xl border border-line bg-surface p-1 shadow-pop outline-none">
        <Menu className="outline-none" onAction={(id) => items.find((item) => item.id === id)?.run()}>
          {items.map((item) => (
            <MenuItem key={item.id} id={item.id} textValue={item.label} className="flex h-8 cursor-pointer items-center gap-2.5 rounded-md px-2 text-[14px] text-fg outline-none data-[focused]:bg-surface-2">
              <Icon name={item.icon} className="size-4 text-fg-3" />
              {item.label}
            </MenuItem>
          ))}
        </Menu>
      </Popover>
    </MenuTrigger>
  );
}

/**
 * Pairs of texts (UTM tags, headers, settings) drawn like the Repeater's table:
 * the column names once, a row of two inputs per pair, no frame of its own.
 */
export function KeyValueField({ node }: { node: FieldNode & { keyLabel?: string; valueLabel?: string; addLabel?: string } }) {
  const id = useId();
  const store = useStore();
  const field = useField(node);
  const rows = (Array.isArray(field.value) ? field.value : []) as { key: string; value: string }[];
  const update = (index: number, key: 'key' | 'value', value: string) => field.setValue(rows.map((row, i) => (i === index ? { ...row, [key]: value } : row)));
  const input = cx(controlClass, 'font-mono text-[13px]', field.readonly && readonlyClass);

  return (
    <FieldFrame node={node} inputId={id} required={field.required} error={field.error}>
      {rows.length > 0 && (
        <div className="flex flex-col gap-2">
          <div aria-hidden="true" className="flex items-center gap-1.5 text-[12.5px] font-medium text-fg-3">
            <span className="min-w-0 flex-1">{node.keyLabel}</span>
            <span className="min-w-0 flex-1">{node.valueLabel}</span>
            {!field.disabled && <span className="w-9 shrink-0" />}
          </div>
          {rows.map((row, index) => (
            <div key={index} className="flex items-center gap-1.5">
              <input
                id={index === 0 ? id : undefined}
                value={row.key}
                onChange={(event) => update(index, 'key', event.target.value)}
                readOnly={field.disabled}
                aria-label={t('inRow', { label: node.keyLabel ?? '', n: index + 1 })}
                className={cx(input, 'min-w-0 flex-1')}
              />
              <input
                value={row.value}
                onChange={(event) => update(index, 'value', event.target.value)}
                readOnly={field.disabled}
                aria-label={t('inRow', { label: node.valueLabel ?? '', n: index + 1 })}
                className={cx(input, 'min-w-0 flex-1')}
              />
              {!field.disabled && (
                <button type="button" className={cx(iconButton, 'size-9 hover:text-danger-fg')} aria-label={removeLabel(index, node.label)} onClick={() => removeRow(store, node.path, rows, index)}>
                  <Icon name="x" />
                </button>
              )}
            </div>
          ))}
        </div>
      )}
      {!field.disabled && (
        <button type="button" className={cx(buttonClass('ghost', 'sm'), '-ml-2.5 self-start text-fg-2')} onClick={() => field.setValue([...rows, { key: '', value: '' }])}>
          <Icon name="plus" />
          {node.addLabel ?? t('add')}
        </button>
      )}
    </FieldFrame>
  );
}

interface PermissionGroup {
  title: string;
  items: { key: string; label: string; sources?: string[] }[];
}

/**
 * Role: each permission on/off. User (tristate): inherit from roles, allow or deny,
 * with the roles that grant it shown next to it.
 */
export function PermissionsField({ node }: { node: FieldNode & { groups?: PermissionGroup[]; tristate?: boolean } }) {
  const field = useField(node);
  const value = (field.value && typeof field.value === 'object' && !Array.isArray(field.value) ? field.value : {}) as Record<string, boolean>;
  const set = (key: string, next: boolean | null) => {
    const copy = { ...value };
    if (next === null) delete copy[key];
    else copy[key] = next;
    field.setValue(copy);
  };

  return (
    <div className="flex flex-col gap-1" data-vanda-path={node.path} {...nodeAttributes(node)}>
      {(node.label || (node.help && !node.helpInline)) && (
        <div className="mb-1 flex items-center justify-between gap-2">
          <span className="text-[13px] font-medium">{node.label}</span>
          {node.help && !node.helpInline && <HelpTip text={node.help} label={node.label} />}
        </div>
      )}
      {node.help && node.helpInline && <span className="mb-1 text-[12.5px] text-fg-3">{node.help}</span>}
      <div className="overflow-hidden rounded-xl border border-line">
        {(node.groups ?? []).map((group) => (
          <div key={group.title}>
            <div className="bg-surface-2 px-4 pt-3 pb-1.5 text-[11.5px] font-semibold tracking-[0.06em] text-fg-3 uppercase">{group.title}</div>
            <ul>
              {group.items.map((item) => {
                const own = item.key in value ? value[item.key] : null;
                const inherited = (item.sources ?? []).length > 0;
                const effective = own ?? inherited;

                return (
                  <li key={item.key} className="flex flex-wrap items-center gap-3 border-t border-line px-4 py-2.5">
                    <div className="flex min-w-0 flex-grow flex-col">
                      <span className="text-[14px] font-medium">{item.label}</span>
                      <span className="font-mono text-[12px] text-fg-3">{item.key}</span>
                    </div>

                    {node.tristate ? (
                      <>
                        <span
                          className={cx(
                            'inline-flex h-[22px] items-center rounded-full border px-2 text-[12px] font-medium whitespace-nowrap',
                            own === null ? 'border-line bg-surface-2 text-fg-2' : own ? 'border-transparent bg-accent-soft text-accent-fg' : 'border-transparent bg-danger-soft text-danger-fg',
                          )}
                        >
                          {own === null ? (inherited ? t('fromRoles', { roles: (item.sources ?? []).join(', ') }) : t('notGranted')) : own ? t('allow') : t('deny')}
                        </span>
                        <div role="radiogroup" aria-label={item.label} className="inline-flex gap-0.5 rounded-lg border border-line bg-surface-2 p-0.5">
                          {([null, true, false] as const).map((option) => (
                            <button
                              key={String(option)}
                              type="button"
                              role="radio"
                              aria-checked={own === option}
                              onClick={() => set(item.key, option)}
                              className={cx(
                                'h-6 rounded-md px-2 text-[12.5px] font-medium text-fg-2 hover:text-fg',
                                own === option && 'bg-surface text-fg shadow-sm ring-1 ring-line',
                              )}
                            >
                              {option === null ? t('inherit') : option ? t('allow') : t('deny')}
                            </button>
                          ))}
                        </div>
                        <span aria-hidden="true" className={cx('size-2 rounded-full', effective ? 'bg-success' : 'bg-line-strong')} />
                      </>
                    ) : (
                      <button
                        type="button"
                        role="switch"
                        aria-checked={Boolean(own)}
                        aria-label={item.label}
                        onClick={() => set(item.key, own ? null : true)}
                        className={cx('flex h-[18px] w-8 shrink-0 items-center rounded-full px-0.5 transition-colors', own ? 'bg-accent' : 'bg-line-strong')}
                      >
                        <span className={cx('size-3.5 rounded-full bg-white shadow transition-transform', own && 'translate-x-3.5')} />
                      </button>
                    )}
                  </li>
                );
              })}
            </ul>
          </div>
        ))}
      </div>
    </div>
  );
}

