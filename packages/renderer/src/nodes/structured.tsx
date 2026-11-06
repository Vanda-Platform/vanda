import { useContext, useId, useRef, useState, type CSSProperties, type KeyboardEvent, type PointerEvent } from 'react';
import { t } from '../i18n';
import { nodeAttributes } from '../store';
import { buttonClass, controlClass, cx, Icon, MOBILE_QUERY, readonlyClass, useMediaQuery } from '../ui';
import { BlockTitleContext } from './blockTitle';
import type { FieldNode, UiNode } from '../types';
import { FieldFrame, useField } from './fields';
import { NodeView } from './NodeView';

type Row = Record<string, unknown>;

const iconButton = 'grid size-7 shrink-0 place-items-center rounded-md text-fg-3 hover:bg-surface-2 hover:text-fg disabled:opacity-30';

type RepeaterNode = FieldNode & {
  fields?: FieldNode[];
  addLabel?: string;
  max?: number;
  /** Chosen on the server from the fields (Repeater::resolvedLayout()). */
  layout?: 'list' | 'table' | 'blocks';
  /** Blocks: "Вариант" gives "Вариант 1", "Вариант 2". */
  itemLabel?: string;
};

/** Fields that need the whole width of a block. */
const WIDE = new Set(['richtext', 'markdown', 'code', 'textarea', 'repeater', 'keyvalue', 'map', 'permissions']);

const same = (a?: string | null, b?: string | null) => Boolean(a && b && a.trim().toLowerCase() === b.trim().toLowerCase());

interface Drag {
  from: number;
  to: number;
  dy: number;
  /** How far the other rows move aside: the dragged row's height plus the gap. */
  step: number;
}

/**
 * Reordering by the grip: dragged with a mouse or a finger (pointer events), or moved with
 * the arrow keys while the grip has focus.
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
      const rects = rows.current.slice(0, count).map((row) => row?.getBoundingClientRect() ?? null);
      const own = rects[index];
      const next = rects[index + 1] ?? rects[index - 1];
      const gap = own && next ? Math.abs((next.top > own.top ? next.top - own.bottom : own.top - next.bottom)) : 8;
      const step = (own?.height ?? 0) + gap;
      update({ from: index, to: index, dy: 0, step });

      const onMove = (moveEvent: globalThis.PointerEvent) => {
        let to = index;
        rects.forEach((rect, i) => {
          if (!rect) return;
          const middle = rect.top + rect.height / 2;
          if (i < index && moveEvent.clientY < middle) to = Math.min(to, i);
          if (i > index && moveEvent.clientY > middle) to = Math.max(to, i);
        });
        update({ from: index, to, dy: moveEvent.clientY - startY, step });
      };
      const onUp = () => {
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

/**
 * Rows of the same fields; child paths are relative to the row ("form.specs.0.name").
 * One border per level: rows never get a box of their own inside the section's card.
 * List: one input per row. Table: columns with the labels once. Blocks: divided groups.
 */
export function RepeaterField({ node }: { node: RepeaterNode }) {
  const labelId = useId();
  const field = useField(node);
  const mobile = useMediaQuery(MOBILE_QUERY);
  const blockTitle = useContext(BlockTitleContext);
  const rows = (Array.isArray(field.value) ? field.value : []) as Row[];
  const templates = node.fields ?? [];
  const canAdd = !field.disabled && (node.max === undefined || rows.length < node.max);
  // A table of inputs does not fit a phone: its rows become blocks there.
  const layout = node.layout === 'table' && mobile ? 'blocks' : (node.layout ?? 'blocks');

  const move = (from: number, to: number) => {
    const next = [...rows];
    const [row] = next.splice(from, 1);
    next.splice(to, 0, row as Row);
    field.setValue(next);
  };
  const remove = (index: number) => field.setValue(rows.filter((_, i) => i !== index));
  const { rows: rowRefs, drag, gripProps, rowStyle } = useRowDrag(rows.length, move);

  const child = (template: FieldNode, index: number, hideLabel: boolean) => {
    const path = `${node.path}.${index}.${template.path}`;
    return <NodeView key={path} node={{ ...template, id: path, path, labelHidden: hideLabel, help: hideLabel ? undefined : template.help } as UiNode} />;
  };

  const grip = (index: number) =>
    !field.disabled && (
      <button
        type="button"
        {...gripProps(index)}
        aria-label={t('move', { label: String(index + 1) })}
        title={t('move', { label: String(index + 1) })}
        className={cx(iconButton, 'size-9 cursor-grab touch-none active:cursor-grabbing', drag?.from === index && 'text-fg')}
      >
        <Icon name="grip" />
      </button>
    );

  const removeButton = (index: number) =>
    !field.disabled && (
      <button type="button" className={cx(iconButton, 'size-9 hover:text-danger-fg')} aria-label={`${t('remove')} ${index + 1}`} onClick={() => remove(index)}>
        <Icon name="x" />
      </button>
    );

  // Help of a single field is said once, under the list, not under every row.
  const helps = [...new Set([...(layout === 'blocks' ? [] : templates.map((template) => template.help)), node.help].filter(Boolean))];

  return (
    <div role="group" aria-labelledby={node.label ? labelId : undefined} className="flex flex-col gap-2" data-vanda-path={node.path} {...nodeAttributes(node)}>
      {node.label && (
        <span id={labelId} className={cx('flex items-center gap-1 text-[13px] font-medium', same(node.label, blockTitle) && 'sr-only')}>
          {node.label}
          {field.required && <span className="text-danger-fg" aria-hidden="true">*</span>}
        </span>
      )}

      {layout === 'table' && rows.length > 0 && (
        <div aria-hidden="true" className="flex items-center gap-1.5 text-[12.5px] font-medium text-fg-3">
          {!field.disabled && <span className="w-9 shrink-0" />}
          {templates.map((template) => (
            <span key={template.path} className="min-w-0 flex-1 truncate">
              {template.label ?? template.path}
              {template.required && <span className="ml-0.5 text-danger-fg">*</span>}
            </span>
          ))}
          {!field.disabled && <span className="w-9 shrink-0" />}
        </div>
      )}

      {rows.length > 0 && (
        <ol className={cx('flex flex-col', layout === 'blocks' ? '' : 'gap-2')}>
          {rows.map((_, index) => (
            <li
              key={index}
              ref={(element) => {
                rowRefs.current[index] = element;
              }}
              style={rowStyle(index)}
              className={cx(
                'bg-surface',
                layout === 'blocks' ? cx('flex flex-col gap-3 py-4', index > 0 && 'border-t border-line', index === 0 && 'pt-1') : 'flex items-start gap-1.5',
                drag?.from === index && 'rounded-lg shadow-pop',
              )}
            >
              {layout === 'blocks' ? (
                <>
                  <div className="flex items-center gap-1">
                    <span className="flex-1 text-[13px] font-medium text-fg-2">{node.itemLabel ? `${node.itemLabel} ${index + 1}` : `№ ${index + 1}`}</span>
                    {grip(index)}
                    {removeButton(index)}
                  </div>
                  <div className={cx('grid gap-3', templates.length > 1 && 'md:grid-cols-2')}>
                    {templates.map((template) => (
                      <div key={template.path} className={cx('min-w-0', WIDE.has(template.type) && 'md:col-span-2')}>
                        {child(template, index, false)}
                      </div>
                    ))}
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
          ))}
        </ol>
      )}

      {canAdd && (
        <div className={cx(layout === 'blocks' ? rows.length > 0 && 'border-t border-line pt-3' : rows.length > 0 && !field.disabled && 'pl-[42px]')}>
          <button type="button" className={cx(buttonClass('ghost', 'sm'), '-ml-2.5 text-fg-2')} onClick={() => field.setValue([...rows, {}])}>
            <Icon name="plus" />
            {node.addLabel ?? t('add')}
          </button>
        </div>
      )}

      {field.error ? (
        <span className="flex items-center gap-1.5 text-[12.5px] text-danger-fg">
          <Icon name="alert" className="size-3.5" />
          {field.error}
        </span>
      ) : (
        helps.length > 0 && <span className={cx('text-[12.5px] text-fg-3', layout !== 'blocks' && rows.length > 0 && !field.disabled && 'pl-[42px]')}>{helps.join(' · ')}</span>
      )}
    </div>
  );
}

/**
 * Pairs of texts (UTM tags, headers, settings) drawn like the Repeater's table:
 * the column names once, a row of two inputs per pair, no frame of its own.
 */
export function KeyValueField({ node }: { node: FieldNode & { keyLabel?: string; valueLabel?: string; addLabel?: string } }) {
  const id = useId();
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
                aria-label={`${node.keyLabel} ${index + 1}`}
                className={cx(input, 'min-w-0 flex-1')}
              />
              <input
                value={row.value}
                onChange={(event) => update(index, 'value', event.target.value)}
                readOnly={field.disabled}
                aria-label={`${node.valueLabel} ${index + 1}`}
                className={cx(input, 'min-w-0 flex-1')}
              />
              {!field.disabled && (
                <button type="button" className={cx(iconButton, 'size-9 hover:text-danger-fg')} aria-label={`${t('remove')} ${index + 1}`} onClick={() => field.setValue(rows.filter((_, i) => i !== index))}>
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
      {node.label && <span className="mb-1 text-[13px] font-medium">{node.label}</span>}
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

