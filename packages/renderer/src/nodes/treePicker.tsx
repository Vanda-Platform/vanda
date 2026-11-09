import { useMemo, useState } from 'react';
import { Button as AriaButton, Dialog, DialogTrigger, Popover } from 'react-aria-components';
import { t } from '../i18n';
import { branch, hasChildren } from '../treeMove';
import { buttonClass, controlClass, cx, Icon, invalidClass } from '../ui';

export interface TreeOption {
  value: string | number;
  label: string;
  parent?: string | number;
  disabled?: boolean;
}

/** Vertical guide lines, one per level, as in the design. */
export function Guides({ depth }: { depth: number }) {
  return Array.from({ length: depth }, (_, index) => (
    <span
      key={index}
      aria-hidden="true"
      className="-my-3 w-6 shrink-0 self-stretch"
      style={{ background: 'linear-gradient(var(--v-line-strong), var(--v-line-strong)) center / 1px 100% no-repeat' }}
    />
  ));
}

/** depth is unused here; treeMove's helpers (branch, hasChildren) expect it. */
type TreeItem = Omit<TreeOption, 'parent'> & { key: string; parent: string | null; depth: number };

/** The options keyed, the chosen keys (a chosen branch means everything inside it) and their paths. */
function useTree(options: TreeOption[], value: unknown, multiple: boolean) {
  const items = useMemo<TreeItem[]>(() => options.map((option) => ({ ...option, key: String(option.value), parent: option.parent === undefined ? null : String(option.parent), depth: 0 })), [options]);
  const byKey = useMemo(() => new Map(items.map((item) => [item.key, item])), [items]);

  const chosen = (multiple ? (Array.isArray(value) ? value : []) : value === null || value === undefined || value === '' ? [] : [value]).map(String);
  const selected = new Set(chosen);
  if (multiple) for (const item of items) if (item.parent && selected.has(item.parent) && !item.disabled) selected.add(item.key);

  const path = (key: string) => {
    const labels: string[] = [];
    for (let item = byKey.get(key); item; item = item.parent ? byKey.get(item.parent) : undefined) labels.unshift(item.label);
    return labels;
  };

  return { items, byKey, selected, path };
}

/**
 * The search box and the tree. In a Select::tree() field it opens from the trigger (TreePicker);
 * a tree filter of a table shows it right in its popover, one overlay instead of two.
 */
export function TreeList({
  label,
  options,
  value,
  onChange,
  multiple = false,
  clearable,
  onPick,
  className,
}: {
  /** Names the tree for screen readers. */
  label: string;
  options: TreeOption[];
  value: unknown;
  onChange: (value: unknown) => void;
  multiple?: boolean;
  clearable: boolean;
  /** After a single value is picked, e.g. to close the popover. */
  onPick?: () => void;
  className?: string;
}) {
  const { items, byKey, selected } = useTree(options, value, multiple);
  const depthOf = (key: string) => {
    let depth = 0;
    for (let item = byKey.get(key); item?.parent; item = byKey.get(item.parent)) depth++;
    return depth;
  };
  const [query, setQuery] = useState('');
  // Start with the branches of the chosen values open.
  const [open, setOpen] = useState<Set<string>>(() => {
    const next = new Set<string>();
    for (const key of selected) for (let item = byKey.get(key); item?.parent; item = byKey.get(item.parent)) next.add(item.parent);
    return next;
  });

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
    onChange(items.filter((item) => next.has(item.key)).map((item) => item.value));
  };

  return (
    <div className={cx('flex flex-col', className)}>
      <div className="relative border-b border-line p-2">
        <Icon name="search" className="pointer-events-none absolute top-1/2 left-4.5 size-4 -translate-y-1/2 text-fg-3" />
        <input autoFocus type="search" value={query} onChange={(event) => setQuery(event.target.value)} placeholder={t('find')} aria-label={t('find')} className={cx(controlClass, 'h-8 pl-8')} />
      </div>
      <div role="tree" aria-label={label} aria-multiselectable={multiple || undefined} className="max-h-80 overflow-y-auto p-1">
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
                  onClick={() =>
                    setOpen((current) => {
                      const next = new Set(current);
                      if (next.has(item.key)) next.delete(item.key);
                      else next.add(item.key);
                      return next;
                    })
                  }
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
                    onChange(valueOf(item.key));
                    onPick?.();
                  }
                }}
                className={cx(
                  'flex h-8 min-w-0 flex-1 items-center gap-2 rounded-lg px-2 text-left text-[13.5px] hover:bg-surface-2 disabled:cursor-not-allowed disabled:text-fg-3 disabled:hover:bg-transparent',
                  !multiple && checked === 'on' && 'bg-accent-soft text-accent-fg hover:bg-accent-soft',
                )}
              >
                {multiple ? (
                  <span aria-hidden="true" className={cx('grid size-4 shrink-0 place-items-center rounded border', checked === 'off' ? 'border-control-line bg-surface' : 'border-accent bg-accent text-on-accent')}>
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
      {(multiple || (clearable && selected.size > 0)) && (
        <div className="flex items-center justify-between border-t border-line px-3 py-2 text-[13px] text-fg-3">
          <span className="tabular-nums">{multiple ? t('selected', { count: selected.size }) : ''}</span>
          <button type="button" className={buttonClass('ghost', 'sm')} onClick={() => onChange(multiple ? [] : null)}>
            {t('clear')}
          </button>
        </div>
      )}
    </div>
  );
}

/** Select::tree(): one value with its path in the trigger, or several as tags; the tree opens below. */
export function TreePicker({
  id,
  label,
  options,
  value,
  onChange,
  multiple = false,
  placeholder,
  clearable,
  disabled,
  invalid,
}: {
  id?: string;
  /** Names the popover for screen readers. */
  label: string;
  options: TreeOption[];
  /** One value, or a list with multiple. */
  value: unknown;
  onChange: (value: unknown) => void;
  multiple?: boolean;
  placeholder?: string;
  /** A single value can be cleared (not a required field). */
  clearable: boolean;
  disabled?: boolean;
  invalid?: boolean;
}) {
  const { byKey, selected, path } = useTree(options, value, multiple);

  // Tags: a fully chosen branch shows as one tag.
  const tags = [...selected].filter((key) => {
    const parent = byKey.get(key)?.parent;
    return !(parent && selected.has(parent));
  });

  const trigger = cx(controlClass, 'flex items-center justify-between gap-2 text-left', invalid && invalidClass, multiple && 'h-auto min-h-(--v-control) py-1');

  return (
    <DialogTrigger>
      <AriaButton id={id} isDisabled={disabled} aria-haspopup="tree" className={trigger}>
        {multiple ? (
          <span className="flex flex-wrap gap-1.5">
            {tags.length === 0 && <span className="text-fg-3">{placeholder ?? t('choose')}</span>}
            {tags.map((key) => (
              <span key={key} className="inline-flex h-6 items-center rounded-md border border-line bg-surface-2 px-2 text-[13px] font-medium">
                {byKey.get(key)?.label ?? key}
              </span>
            ))}
          </span>
        ) : selected.size > 0 ? (
          <span className="flex min-w-0 items-center gap-1 truncate">
            {path([...selected][0]!).map((part, index, all) => (
              <span key={index} className="flex items-center gap-1">
                <span className={index === all.length - 1 ? 'text-fg' : 'text-fg-3'}>{part}</span>
                {index < all.length - 1 && <Icon name="chevron-right" className="size-3.5 text-fg-3" />}
              </span>
            ))}
          </span>
        ) : (
          <span className="text-fg-3">{placeholder ?? t('choose')}</span>
        )}
        <Icon name="chevron-down" className="size-4 shrink-0 text-fg-3" />
      </AriaButton>
      <Popover placement="bottom start" className="w-(--trigger-width) min-w-72 rounded-xl border border-line bg-surface shadow-pop outline-none">
        <Dialog aria-label={label} className="outline-none">
          {({ close }) => <TreeList label={label} options={options} value={value} onChange={onChange} multiple={multiple} clearable={clearable} onPick={close} />}
        </Dialog>
      </Popover>
    </DialogTrigger>
  );
}
