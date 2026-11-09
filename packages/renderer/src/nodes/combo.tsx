import { useEffect, useId, useMemo, useRef, useState, type KeyboardEvent } from 'react';
import { Button as AriaButton, ComboBox, Group, Input, ListBox, ListBoxItem, Popover, type Key } from 'react-aria-components';
import { t } from '../i18n';
import { useStore } from '../store';
import { options as fetchOptions } from '../transport';
import { controlClass, cx, Icon, invalidClass, readonlyClass, Spinner } from '../ui';
import type { FieldNode, Option } from '../types';
import { FieldFrame, useField } from './fields';

type ComboNode = FieldNode & {
  /** Select: every option is known in the browser. */
  options?: Option[];
  /** Relation: labels of the chosen records; the rest comes from the server as the user types. */
  selected?: Option[];
  multiple?: boolean;
  allowCustom?: boolean;
};

/** The extra row that adds what the user typed (Select::allowCustom()). */
const CUSTOM = '\u0000custom';

const popoverClass = 'min-w-(--trigger-width) overflow-auto rounded-xl border border-line bg-surface p-1 shadow-pop outline-none';
const optionClass =
  'flex min-h-8 cursor-pointer items-center justify-between gap-3 rounded-md px-2 py-1 text-[14px] text-fg outline-none data-[focused]:bg-surface-2 data-[selected]:font-medium data-[selected]:text-accent-fg';

const toList = (value: unknown): (string | number)[] =>
  (Array.isArray(value) ? value : value === null || value === undefined || value === '' ? [] : [value]).filter((item): item is string | number => typeof item === 'string' || typeof item === 'number');

/**
 * A field you type into to find an option: a searchable Select (options known here)
 * or a Relation (options found by the server, Relation::search()).
 */
export function ComboField({ node, remote = false }: { node: ComboNode; remote?: boolean }) {
  const id = useId();
  const store = useStore();
  const field = useField(node);
  const values = toList(field.value);
  const [labels, setLabels] = useState<Record<string, Option>>({});
  const [query, setQuery] = useState('');
  const [found, setFound] = useState<Option[]>([]);
  const [loading, setLoading] = useState(false);
  const [open, setOpen] = useState(false);
  const typed = useRef(false);

  // Labels of chosen values survive a new search that no longer finds them.
  useEffect(() => {
    const known = [...(node.options ?? []), ...(node.selected ?? [])];
    setLabels((current) => ({ ...current, ...Object.fromEntries(known.map((option) => [String(option.value), option])) }));
  }, [node.options, node.selected]);

  const labelOf = (value: string | number) => labels[String(value)]?.label ?? String(value);
  const single = node.multiple ? undefined : values[0];

  // A single field shows the chosen label until the user starts typing.
  useEffect(() => {
    if (!node.multiple && !typed.current) setQuery(single === undefined ? '' : labelOf(single));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [single, labels]);

  useEffect(() => {
    if (!remote || !open) return;
    const controller = new AbortController();
    const timer = window.setTimeout(() => {
      setLoading(true);
      fetchOptions(store.get().page, node.path, typed.current ? query : '', controller.signal)
        .then(({ data }) => {
          setFound(data.options ?? []);
          setLabels((current) => ({ ...current, ...Object.fromEntries((data.options ?? []).map((option) => [String(option.value), option])) }));
        })
        .catch(() => undefined)
        .finally(() => !controller.signal.aborted && setLoading(false));
    }, 200);
    return () => {
      window.clearTimeout(timer);
      controller.abort();
    };
  }, [remote, open, query, node.path, store]);

  const term = typed.current ? query.trim().toLowerCase() : '';
  const items = useMemo(() => {
    const source = remote ? found : (node.options ?? []).filter((option) => !term || `${option.label} ${option.hint ?? ''}`.toLowerCase().includes(term));
    const list = source.filter((option) => !node.multiple || !values.some((value) => String(value) === String(option.value))).map((option) => ({ id: String(option.value), ...option }));
    const exact = (node.options ?? []).some((option) => option.label.toLowerCase() === term || String(option.value).toLowerCase() === term);
    return node.allowCustom && term && !exact ? [{ id: CUSTOM, value: query.trim(), label: t('addCustom', { value: query.trim() }) }, ...list] : list;
  }, [remote, found, node.options, node.multiple, node.allowCustom, values, term, query]);

  const choose = (key: Key | null) => {
    if (key === null) return;
    const item = items.find((entry) => entry.id === String(key));
    if (!item) return;
    const option: Option = item.id === CUSTOM ? { value: item.value, label: String(item.value) } : item;
    setLabels((current) => ({ ...current, [String(option.value)]: option }));
    typed.current = false;

    if (node.multiple) {
      if (!values.some((value) => String(value) === String(option.value))) field.setValue([...values, option.value]);
      setQuery('');
    } else {
      field.setValue(option.value);
      setQuery(option.label);
    }
  };

  const remove = (value: string | number) => field.setValue(values.filter((entry) => String(entry) !== String(value)));

  const onKeyDown = (event: KeyboardEvent<HTMLInputElement>) => {
    if (node.multiple && event.key === 'Backspace' && query === '' && values.length > 0) remove(values[values.length - 1]!);
  };

  return (
    <FieldFrame node={node} inputId={id} required={field.required} error={field.error}>
      <ComboBox
        aria-label={node.label ?? node.path}
        items={items}
        inputValue={query}
        onInputChange={(text) => {
          typed.current = true;
          setQuery(text);
        }}
        selectedKey={single === undefined ? null : String(single)}
        onSelectionChange={choose}
        onOpenChange={(next) => {
          setOpen(next);
          if (!next && !node.multiple) {
            typed.current = false;
            setQuery(single === undefined ? '' : labelOf(single));
          }
        }}
        menuTrigger="focus"
        allowsEmptyCollection
        isDisabled={field.disabled}
        isInvalid={Boolean(field.error)}
      >
        <Group className={cx(controlClass, 'flex h-auto min-h-(--v-control) flex-wrap items-center gap-1.5 py-1 pr-1 focus-within:border-accent focus-within:ring-3 focus-within:ring-ring', field.error && invalidClass, field.readonly && readonlyClass)}>
          {node.multiple &&
            values.map((value) => (
              <span key={String(value)} className="inline-flex h-6 items-center gap-1 rounded-md border border-line bg-surface-2 pr-0.5 pl-2 text-[13px] font-medium">
                {labelOf(value)}
                {!field.disabled && (
                  <button type="button" aria-label={t('removeFile', { name: labelOf(value) })} onClick={() => remove(value)} className="grid size-5 place-items-center rounded text-fg-3 hover:bg-surface hover:text-fg">
                    <Icon name="x" className="size-3" />
                  </button>
                )}
              </span>
            ))}
          <Input
            id={id}
            onKeyDown={onKeyDown}
            placeholder={values.length > 0 && node.multiple ? undefined : (node.placeholder ?? t(remote ? 'typeToFind' : 'choose'))}
            className="h-7 min-w-[8ch] flex-1 bg-transparent text-[14px] outline-none placeholder:text-fg-3"
          />
          <span className="ml-auto flex items-center gap-0.5 text-fg-3">
            {(loading || field.pending) && <Spinner className="size-3" />}
            {!node.multiple && single !== undefined && !field.required && !field.disabled && (
              <button type="button" aria-label={t('clear')} onClick={() => (field.setValue(null), setQuery(''))} className="grid size-7 place-items-center rounded-md hover:bg-surface-2 hover:text-fg">
                <Icon name="x" />
              </button>
            )}
            {!field.readonly && (
              <AriaButton className="grid size-7 place-items-center rounded-md hover:bg-surface-2 hover:text-fg">
                <Icon name="chevron-down" />
              </AriaButton>
            )}
          </span>
        </Group>
        <Popover className={popoverClass}>
          <ListBox
            className="max-h-72 outline-none"
            renderEmptyState={() => <div className="px-2 py-1.5 text-[13.5px] text-fg-3">{loading ? t('loading') : t('noResults')}</div>}
          >
            {(item: Option & { id: string }) => (
              <ListBoxItem id={item.id} textValue={item.id === CUSTOM ? String(item.value) : item.label} className={cx(optionClass, item.id === CUSTOM && 'text-accent-fg')}>
                {({ isSelected }) => (
                  <>
                    <span className="flex min-w-0 items-center gap-2">
                      {item.id === CUSTOM && <Icon name="plus" className="size-3.5" />}
                      <span className="truncate">{item.label}</span>
                      {item.hint && <span className="shrink-0 text-fg-3">{item.hint}</span>}
                    </span>
                    {isSelected && <Icon name="check" />}
                  </>
                )}
              </ListBoxItem>
            )}
          </ListBox>
        </Popover>
      </ComboBox>
    </FieldFrame>
  );
}
