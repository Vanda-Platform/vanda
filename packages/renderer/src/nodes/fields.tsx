import { useId, useState } from 'react';
import {
  Button as AriaButton,
  Checkbox as AriaCheckbox,
  ListBox,
  ListBoxItem,
  Popover,
  Radio as AriaRadio,
  RadioGroup,
  Select as AriaSelect,
  SelectValue,
  Slider,
  SliderThumb,
  SliderTrack,
  Switch as AriaSwitch,
} from 'react-aria-components';
import { OptionText, optionClass, popoverClass } from '../choice';
import { getPath, isTrue } from '../expr';
import { t } from '../i18n';
import { nodeAttributes, useSnapshot, useStore } from '../store';
import { controlClass, cx, Icon, invalidClass, readonlyClass, Spinner } from '../ui';
import type { FieldNode, Option } from '../types';
import { ComboField } from './combo';
import { maskedChange } from '../mask';
import { describedBy, helpPlace } from '../fieldHelp';
import { HelpTip } from './help';

export function useField(node: FieldNode) {
  const store = useStore();
  const value = useSnapshot((s) => getPath(s.page.state, node.path));
  const errors = useSnapshot((s) => s.errors[node.path]);
  const state = useSnapshot((s) => s.page.state);
  const pending = useSnapshot((s) => s.pending.has(node.id));

  const readonly = Boolean(node.readonly);

  return {
    value,
    error: errors?.[0],
    pending,
    required: !readonly && (Boolean(node.required) || (node.requiredWhen !== undefined && isTrue(node.requiredWhen, state))),
    /** Nothing may change the value: disabled, read-only or computed. */
    disabled: readonly || Boolean(node.disabled) || (node.disabledWhen !== undefined && isTrue(node.disabledWhen, state)) || node.computed !== undefined,
    /** Shown as a value (readonlyClass), not dimmed; text inputs keep their text selectable. */
    readonly,
    setValue: (next: unknown) => store.setValue(node.path, next),
  };
}

export function FieldFrame({
  node,
  inputId,
  required,
  error,
  children,
  counter,
  as = 'div',
}: {
  node: FieldNode;
  inputId: string;
  required: boolean;
  error?: string;
  children: React.ReactNode;
  counter?: string;
  as?: 'div' | 'fieldset';
}) {
  const Root = as;
  const helpId = `${inputId}-help`;
  // The hint (Field::help()): an «i» by the label (or beside a field without one), text under the field with inline.
  const place = helpPlace(node);
  const tip = node.help && (place === 'label' || place === 'beside') ? <HelpTip text={node.help} label={node.label} describedBy={helpId} /> : null;
  const showInline = place === 'inline' && !error;
  const caption = (
    <>
      {node.label}
      {required && <span className="text-danger-fg" aria-hidden="true">*</span>}
    </>
  );

  return (
    <Root className="m-0 flex min-w-0 flex-col gap-1.5 border-0 p-0" data-vanda-path={node.path} {...nodeAttributes(node)}>
      {node.label &&
        (as === 'fieldset' ? (
          <legend className="mb-1.5 flex w-full items-center gap-1 text-[13px] font-medium text-fg">
            {caption}
            {place === 'label' && <span className="ml-auto flex">{tip}</span>}
          </legend>
        ) : place === 'label' ? (
          <div className="flex items-center justify-between gap-2">
            <label htmlFor={inputId} className="flex min-w-0 items-center gap-1 text-[13px] font-medium text-fg">
              {caption}
            </label>
            {tip}
          </div>
        ) : (
          <label htmlFor={inputId} className={cx('flex items-center gap-1 text-[13px] font-medium text-fg', node.labelHidden && 'sr-only')}>
            {caption}
          </label>
        ))}
      {place === 'beside' ? (
        <div className="flex items-start gap-1.5">
          <div className="flex min-w-0 flex-1 flex-col gap-1.5">{children}</div>
          <span className="flex h-(--v-control) shrink-0 items-center">{tip}</span>
        </div>
      ) : (
        children
      )}
      {/* Screen readers hear the hint with the field (aria-describedby), wherever the eyes see it. */}
      {node.help && !showInline && (
        <span id={helpId} className="sr-only">
          {node.help}
        </span>
      )}
      {error ? (
        <span id={`${inputId}-error`} className="flex items-center gap-1.5 text-[12.5px] text-danger-fg">
          <Icon name="alert" className="size-3.5" />
          {error}
        </span>
      ) : (
        (showInline || counter) && (
          <span className="flex justify-between gap-2 text-[12.5px] text-fg-3">
            {showInline ? <span id={helpId}>{node.help}</span> : <span />}
            {counter && <span className="tabular-nums">{counter}</span>}
          </span>
        )
      )}
    </Root>
  );
}

const text = (value: unknown) => (value === null || value === undefined ? '' : String(value));

export function InputField({
  node,
}: {
  node: FieldNode & { inputType?: string; prefix?: string; suffix?: string; maxLength?: number; mask?: string; suggestions?: string[]; min?: number; max?: number; step?: number };
}) {
  const id = useId();
  const field = useField(node);
  const input = (
    <input
      id={id}
      type={node.inputType ?? 'text'}
      value={text(field.value)}
      onChange={(event) => field.setValue(node.mask ? maskedChange(node.mask, text(field.value), event.target.value) : event.target.value)}
      placeholder={node.placeholder ?? (node.mask ? node.mask.replace(/[9a*]/g, '_') : undefined)}
      inputMode={node.mask && !/[a*]/.test(node.mask) ? 'numeric' : undefined}
      list={node.suggestions ? `${id}-list` : undefined}
      min={node.min}
      max={node.max}
      step={node.step}
      maxLength={node.maxLength}
      disabled={field.disabled && !field.readonly}
      readOnly={field.readonly}
      required={field.required}
      aria-invalid={field.error ? true : undefined}
      aria-describedby={describedBy(id, field.error, node.help)}
      className={cx(controlClass, field.error && invalidClass, field.readonly && readonlyClass, (node.prefix || node.suffix) && 'shadow-none', node.prefix && 'rounded-l-none', node.suffix && 'rounded-r-none')}
    />
  );

  return (
    <FieldFrame node={node} inputId={id} required={field.required} error={field.error}>
      {node.suggestions && (
        <datalist id={`${id}-list`}>
          {node.suggestions.map((suggestion) => (
            <option key={suggestion} value={suggestion} />
          ))}
        </datalist>
      )}
      {node.prefix || node.suffix ? (
        <div className="flex">
          {node.prefix && <span className="flex items-center rounded-l-lg border border-r-0 border-control-line bg-surface-2 px-2.5 text-fg-2">{node.prefix}</span>}
          {input}
          {node.suffix && <span className="flex items-center rounded-r-lg border border-l-0 border-control-line bg-surface-2 px-2.5 text-fg-2">{node.suffix}</span>}
        </div>
      ) : (
        input
      )}
    </FieldFrame>
  );
}

function strength(password: string): 0 | 1 | 2 | 3 | 4 {
  let score = 0;
  if (password.length >= 8) score++;
  if (password.length >= 12) score++;
  if (/[a-zа-я]/.test(password) && /[A-ZА-Я]/.test(password)) score++;
  if (/\d/.test(password) && /[^\w\s]/.test(password)) score++;
  return score as 0 | 1 | 2 | 3 | 4;
}

export function PasswordField({ node }: { node: FieldNode & { strength?: boolean } }) {
  const id = useId();
  const field = useField(node);
  const [visible, setVisible] = useState(false);
  const value = text(field.value);
  const score = strength(value);
  const level = score <= 1 ? 'weak' : score <= 2 ? 'fair' : 'strong';
  const color = { weak: 'bg-danger', fair: 'bg-warning', strong: 'bg-success' }[level];

  return (
    <FieldFrame node={node} inputId={id} required={field.required} error={field.error}>
      <div className="relative flex items-center">
        <input
          id={id}
          type={visible ? 'text' : 'password'}
          value={value}
          autoComplete={node.strength ? 'new-password' : 'current-password'}
          onChange={(event) => field.setValue(event.target.value)}
          disabled={field.disabled && !field.readonly}
          readOnly={field.readonly}
          aria-invalid={field.error ? true : undefined}
          aria-describedby={describedBy(id, field.error, node.help)}
          className={cx(controlClass, 'pr-10', field.error && invalidClass, field.readonly && readonlyClass)}
        />
        <button
          type="button"
          onClick={() => setVisible(!visible)}
          aria-label={visible ? t('hidePassword') : t('showPassword')}
          className="absolute right-1.5 grid size-7 place-items-center rounded-md text-fg-3 hover:bg-surface-2 hover:text-fg"
        >
          <Icon name={visible ? 'eye-off' : 'eye'} />
        </button>
      </div>
      {node.strength && value !== '' && (
        <div className="flex items-center gap-2.5" aria-live="polite">
          <div aria-hidden="true" className="grid flex-grow grid-cols-4 gap-1">
            {[1, 2, 3, 4].map((step) => (
              <span key={step} className={cx('h-1 rounded-sm', step <= Math.max(score, 1) ? color : 'bg-surface-3')} />
            ))}
          </div>
          <span className={cx('text-[12.5px] font-medium', { weak: 'text-danger-fg', fair: 'text-warning-fg', strong: 'text-success-fg' }[level])}>{t(level)}</span>
        </div>
      )}
    </FieldFrame>
  );
}

export function TextAreaField({ node }: { node: FieldNode & { rows?: number; maxLength?: number } }) {
  const id = useId();
  const field = useField(node);
  const value = text(field.value);

  return (
    <FieldFrame node={node} inputId={id} required={field.required} error={field.error} counter={node.maxLength ? `${value.length} / ${node.maxLength}` : undefined}>
      <textarea
        id={id}
        rows={node.rows ?? 4}
        value={value}
        onChange={(event) => field.setValue(event.target.value)}
        placeholder={node.placeholder}
        maxLength={node.maxLength}
        disabled={field.disabled && !field.readonly}
        readOnly={field.readonly}
        aria-invalid={field.error ? true : undefined}
        aria-describedby={describedBy(id, field.error, node.help)}
        className={cx(controlClass, 'h-auto resize-y py-2 leading-relaxed', field.error && invalidClass, field.readonly && 'resize-none', field.readonly && readonlyClass)}
      />
    </FieldFrame>
  );
}


/**
 * The chosen option in a closed field: its label, then its hint and description muted on the same line
 * (the description gives way first when the field is narrow).
 */
export function ChosenText({ option }: { option: Pick<Option, 'label' | 'hint' | 'description'> }) {
  return (
    <span className="flex min-w-0 flex-1 items-baseline gap-2">
      <span className="truncate">
        {option.label}
        {option.hint && <span className="ml-2 text-fg-3">{option.hint}</span>}
      </span>
      {option.description && <span className="min-w-0 flex-1 truncate text-[13px] text-fg-3">{option.description}</span>}
    </span>
  );
}

export function SelectField({ node }: { node: FieldNode & { options?: Option[]; multiple?: boolean; searchable?: boolean; allowCustom?: boolean } }) {
  // Long lists and custom values need a text box to type into.
  if (node.searchable || node.allowCustom) return <ComboField node={node} />;
  return <PlainSelect node={node} />;
}

function PlainSelect({ node }: { node: FieldNode & { options?: Option[]; multiple?: boolean } }) {
  const id = useId();
  const field = useField(node);
  const options = node.options ?? [];
  const byKey = (key: unknown) => options.find((option) => String(option.value) === String(key));
  const items = options.map((option) => ({ id: String(option.value), ...option }));
  const trigger = cx(
    controlClass,
    'flex items-center justify-between gap-2 text-left data-[focus-visible]:border-accent data-[focus-visible]:ring-3 data-[focus-visible]:ring-ring',
    field.error && invalidClass,
    field.readonly && readonlyClass,
  );
  const list = (
    <Popover className={popoverClass}>
      <ListBox items={items} className="max-h-72 outline-none">
        {(option) => (
          <ListBoxItem id={option.id} textValue={option.label} className={optionClass}>
            {({ isSelected }) => (
              <>
                <OptionText option={option} />
                {isSelected && <Icon name="check" className="shrink-0" />}
              </>
            )}
          </ListBoxItem>
        )}
      </ListBox>
    </Popover>
  );

  if (node.multiple) {
    const selected = (Array.isArray(field.value) ? field.value : []).map(String);

    return (
      <FieldFrame node={node} inputId={id} required={field.required} error={field.error}>
        <AriaSelect
          id={id}
          aria-label={node.label ?? node.path}
          aria-describedby={describedBy(id, field.error, node.help)}
          selectionMode="multiple"
          value={selected}
          onChange={(keys) => field.setValue(keys.map((key) => byKey(key)?.value ?? key))}
          isDisabled={field.disabled}
        >
          <AriaButton className={cx(trigger, 'h-auto min-h-(--v-control) py-1')}>
            <span className="flex flex-wrap gap-1.5">
              {selected.length === 0 && <span className="text-fg-3">{node.placeholder ?? t('choose')}</span>}
              {selected.map((key) => (
                <span key={key} title={byKey(key)?.description} className="inline-flex h-6 items-center rounded-md border border-line bg-surface-2 px-2 text-[13px] font-medium">
                  {byKey(key)?.label ?? key}
                </span>
              ))}
            </span>
            {!field.readonly && <Icon name="chevron-down" className="size-4 shrink-0 text-fg-3" />}
          </AriaButton>
          {list}
        </AriaSelect>
      </FieldFrame>
    );
  }

  const current = byKey(field.value ?? '');

  return (
    <FieldFrame node={node} inputId={id} required={field.required} error={field.error}>
      <AriaSelect
        id={id}
        aria-label={node.label ?? node.path}
        aria-describedby={describedBy(id, field.error, node.help)}
        value={current ? String(current.value) : null}
        onChange={(key) => field.setValue(byKey(key)?.value ?? null)}
        isDisabled={field.disabled}
        isInvalid={Boolean(field.error)}
        placeholder={node.placeholder ?? t('choose')}
      >
        <AriaButton className={trigger}>
          {current ? (
            <ChosenText option={current} />
          ) : (
            <SelectValue className="truncate data-[placeholder]:text-fg-3" />
          )}
          <span className="flex items-center gap-2 text-fg-3">
            {field.pending && <Spinner className="size-3" />}
            {!field.readonly && <Icon name="chevron-down" />}
          </span>
        </AriaButton>
        {list}
      </AriaSelect>
    </FieldFrame>
  );
}

/**
 * The hint of a field that draws its own label (checkbox, switch, radio, slider): the «i» to put at the
 * end of its label row (null when the hint is inline or missing), the text under it with inline, and
 * the hidden copy screen readers get through `id`.
 */
function useHint(node: FieldNode) {
  const id = `${useId()}-help`;
  const place = helpPlace(node);
  const help = node.help ?? '';
  return {
    id: node.help ? id : undefined,
    tip: place === 'label' || place === 'beside' ? <HelpTip text={help} label={node.label} describedBy={id} /> : null,
    inline: place === 'inline' ? (
      <span id={id} className="text-[12.5px] text-fg-3">
        {help}
      </span>
    ) : null,
    hidden:
      node.help && place !== 'inline' ? (
        <span id={id} className="sr-only">
          {help}
        </span>
      ) : null,
  };
}

/** The label row of a group of options: the label at the start, the hint's «i» at the end. */
function GroupLabel({ label, tip, className }: { label?: string; tip: React.ReactNode; className?: string }) {
  if (!label && !tip) return null;
  return (
    <div className={cx('flex items-center justify-between gap-2', className)}>
      <span className="text-[13px] font-medium">{label}</span>
      {tip}
    </div>
  );
}

const box =
  'grid size-4 shrink-0 place-items-center rounded-[4.5px] border border-control-line bg-surface text-on-accent shadow-sm transition group-data-[selected]:border-accent group-data-[selected]:bg-accent group-data-[focus-visible]:ring-3 group-data-[focus-visible]:ring-ring';

export function CheckboxField({ node }: { node: FieldNode }) {
  const field = useField(node);
  const hint = useHint(node);

  return (
    <div className="flex flex-col gap-1.5" data-vanda-path={node.path} {...nodeAttributes(node)}>
      <div className="flex items-start gap-1">
        <AriaCheckbox
          isSelected={Boolean(field.value)}
          onChange={(selected) => field.setValue(selected)}
          isDisabled={field.disabled}
          aria-describedby={hint.id}
          className="group flex cursor-pointer items-start gap-2.5 text-[14px] data-[disabled]:cursor-not-allowed data-[disabled]:opacity-60"
        >
          <span className={cx(box, 'mt-[3px]')}>
            <Icon name="check" className="size-3 opacity-0 group-data-[selected]:opacity-100" />
          </span>
          <span className="flex flex-col">
            <span>{node.label}</span>
            {hint.inline}
          </span>
        </AriaCheckbox>
        {hint.tip && <span className="mt-[3px] flex">{hint.tip}</span>}
      </div>
      {hint.hidden}
      {field.error && <span className="text-[12.5px] text-danger-fg">{field.error}</span>}
    </div>
  );
}

export function SwitchField({ node }: { node: FieldNode }) {
  const field = useField(node);
  const hint = useHint(node);

  return (
    <div className="flex flex-col gap-1.5" data-vanda-path={node.path} {...nodeAttributes(node)}>
      <div className="flex items-start gap-1">
        <AriaSwitch
          isSelected={Boolean(field.value)}
          onChange={(selected) => field.setValue(selected)}
          isDisabled={field.disabled}
          aria-describedby={hint.id}
          className="group flex cursor-pointer items-start gap-2.5 text-[14px] data-[disabled]:cursor-not-allowed data-[disabled]:opacity-60"
        >
          <span className="mt-0.5 flex h-[18px] w-8 shrink-0 items-center rounded-full bg-line-strong px-0.5 transition-colors group-data-[selected]:bg-accent group-data-[focus-visible]:ring-3 group-data-[focus-visible]:ring-ring">
            <span className="size-3.5 rounded-full bg-white shadow transition-transform group-data-[selected]:translate-x-3.5" />
          </span>
          <span className="flex flex-col">
            <span className="font-medium">{node.label}</span>
            {hint.inline}
          </span>
        </AriaSwitch>
        {hint.tip && <span className="mt-[3px] flex">{hint.tip}</span>}
      </div>
      {hint.hidden}
      {field.error && <span className="text-[12.5px] text-danger-fg">{field.error}</span>}
    </div>
  );
}

export function RadioField({ node }: { node: FieldNode & { options?: Option[]; inline?: boolean } }) {
  const field = useField(node);
  const hint = useHint(node);
  const radioIds = useId();
  const options = node.options ?? [];

  return (
    <div className="flex flex-col gap-1.5" data-vanda-path={node.path} {...nodeAttributes(node)}>
      {/* The label row stays out of the radio group: the «i» is a button of its own, not one of the options. */}
      <GroupLabel label={node.label} tip={hint.tip} className="mb-0.5" />
      <RadioGroup
        aria-label={node.label ?? node.path}
        aria-describedby={hint.id}
        value={field.value === null || field.value === undefined ? null : String(field.value)}
        onChange={(key) => field.setValue(options.find((option) => String(option.value) === key)?.value ?? key)}
        isDisabled={field.disabled}
        orientation={node.inline ? 'horizontal' : 'vertical'}
        className="flex flex-col gap-2"
      >
        <div className={cx('flex gap-2.5', node.inline ? 'flex-row flex-wrap gap-x-5' : 'flex-col')}>
          {options.map((option) => (
            <AriaRadio
              key={String(option.value)}
              value={String(option.value)}
              aria-describedby={option.description ? `${radioIds}-${String(option.value)}` : undefined}
              className={cx('group flex cursor-pointer gap-2.5 text-[14px]', option.description ? 'items-start' : 'items-center')}
            >
              <span className={cx('size-4 shrink-0 rounded-full border border-control-line bg-surface shadow-sm transition group-data-[selected]:border-[5px] group-data-[selected]:border-accent group-data-[focus-visible]:ring-3 group-data-[focus-visible]:ring-ring', option.description && 'mt-[3px]')} />
              {option.description ? (
                <span className="flex min-w-0 flex-col">
                  {option.label}
                  <span id={`${radioIds}-${String(option.value)}`} className="text-[12.5px] leading-[1.35] text-fg-3">
                    {option.description}
                  </span>
                </span>
              ) : (
                option.label
              )}
            </AriaRadio>
          ))}
        </div>
      </RadioGroup>
      {hint.inline}
      {hint.hidden}
      {field.error && <span className="text-[12.5px] text-danger-fg">{field.error}</span>}
    </div>
  );
}

export function SegmentedField({ node }: { node: FieldNode & { options?: Option[] } }) {
  const field = useField(node);
  const hint = useHint(node);
  const options = node.options ?? [];

  return (
    <div className="flex flex-col gap-1.5" data-vanda-path={node.path} {...nodeAttributes(node)}>
      <GroupLabel label={node.label} tip={hint.tip} />
      <RadioGroup
        aria-label={node.label ?? node.path}
        aria-describedby={hint.id}
        orientation="horizontal"
        value={field.value === null || field.value === undefined ? null : String(field.value)}
        onChange={(key) => field.setValue(options.find((option) => String(option.value) === key)?.value ?? key)}
        isDisabled={field.disabled}
        className="flex flex-col gap-1.5"
      >
        <div className="inline-flex gap-0.5 self-start rounded-lg border border-line bg-surface-2 p-0.5">
          {options.map((option) => (
            <AriaRadio
              key={String(option.value)}
              value={String(option.value)}
              className="flex h-[30px] cursor-pointer items-center rounded-md px-2.5 text-[13px] font-medium text-fg-2 outline-none hover:text-fg data-[focus-visible]:ring-3 data-[focus-visible]:ring-ring data-[selected]:bg-surface data-[selected]:text-fg data-[selected]:shadow-sm data-[selected]:ring-1 data-[selected]:ring-line"
            >
              {option.label}
            </AriaRadio>
          ))}
        </div>
      </RadioGroup>
      {hint.inline}
      {hint.hidden}
      {field.error && <span className="text-[12.5px] text-danger-fg">{field.error}</span>}
    </div>
  );
}

export function RangeField({ node }: { node: FieldNode & { min?: number; max?: number; step?: number; suffix?: string } }) {
  const field = useField(node);
  const hint = useHint(node);
  const min = node.min ?? 0;
  const max = node.max ?? 100;
  const value = typeof field.value === 'number' ? field.value : Number(field.value ?? min) || min;

  return (
    <div className="flex flex-col gap-2" data-vanda-path={node.path} {...nodeAttributes(node)}>
      <Slider
        value={value}
        onChange={(next) => field.setValue(next)}
        minValue={min}
        maxValue={max}
        step={node.step ?? 1}
        isDisabled={field.disabled}
        aria-label={node.label ?? node.path}
        aria-describedby={hint.id}
      >
        <div className="mb-2 flex items-center justify-between gap-2 text-[13px]">
          {/* The value takes the end of the row: the «i» follows the label. */}
          <span className="flex items-center gap-1 font-medium">
            {node.label}
            {hint.tip}
          </span>
          <span className="text-fg-2 tabular-nums">
            {value}
            {node.suffix ? ` ${node.suffix}` : ''}
          </span>
        </div>
        <SliderTrack className="relative h-5 w-full">
          {({ state }) => (
            <>
              <div className="absolute top-1/2 h-1.5 w-full -translate-y-1/2 rounded-full bg-surface-3" />
              <div className="absolute top-1/2 h-1.5 -translate-y-1/2 rounded-full bg-accent" style={{ width: `${state.getThumbPercent(0) * 100}%` }} />
              <SliderThumb className="top-1/2 size-4 rounded-full border-2 border-accent bg-surface shadow data-[focus-visible]:ring-3 data-[focus-visible]:ring-ring" />
            </>
          )}
        </SliderTrack>
      </Slider>
      {hint.inline}
      {hint.hidden}
    </div>
  );
}

export function NumberRangeField({ node }: { node: FieldNode & { suffix?: string } }) {
  const id = useId();
  const field = useField(node);
  const value = (field.value ?? {}) as { from?: number | string | null; to?: number | string | null };
  const update = (key: 'from' | 'to', next: string) => field.setValue({ ...value, [key]: next === '' ? null : Number(next) });

  return (
    <FieldFrame node={node} inputId={id} required={field.required} error={field.error}>
      <div className="grid grid-cols-2 gap-2">
        {(['from', 'to'] as const).map((key) => (
          <div key={key} className="flex">
            <span className="flex items-center rounded-l-lg border border-r-0 border-control-line bg-surface-2 px-2.5 text-fg-2">{t(key)}</span>
            <input
              id={key === 'from' ? id : undefined}
              type="number"
              inputMode="decimal"
              value={text(value[key])}
              onChange={(event) => update(key, event.target.value)}
              disabled={field.disabled}
              aria-label={`${node.label ?? ''} ${t(key)}`}
              aria-describedby={key === 'from' ? describedBy(id, field.error, node.help) : undefined}
              className={cx(controlClass, 'rounded-l-none tabular-nums', node.suffix && 'rounded-r-none')}
            />
            {node.suffix && <span className="flex items-center rounded-r-lg border border-l-0 border-control-line bg-surface-2 px-2.5 text-fg-2">{node.suffix}</span>}
          </div>
        ))}
      </div>
    </FieldFrame>
  );
}

export function ColorField({ node }: { node: FieldNode & { presets?: string[] } }) {
  const id = useId();
  const field = useField(node);
  const value = text(field.value) || '#000000';

  return (
    <FieldFrame node={node} inputId={id} required={field.required} error={field.error}>
      <div className="flex items-center gap-2">
        <input
          type="color"
          value={value}
          onChange={(event) => field.setValue(event.target.value.toUpperCase())}
          aria-label={node.label ?? node.path}
          disabled={field.disabled}
          className="h-(--v-control) w-10 shrink-0 cursor-pointer rounded-lg border border-control-line bg-surface p-1"
        />
        <input
          id={id}
          value={text(field.value)}
          onChange={(event) => field.setValue(event.target.value)}
          disabled={field.disabled}
          aria-describedby={describedBy(id, field.error, node.help)}
          className={cx(controlClass, 'font-mono text-[13.5px]')}
        />
      </div>
      {node.presets && node.presets.length > 0 && (
        <div className="flex gap-1.5" role="group" aria-label={node.label}>
          {node.presets.map((preset) => (
            <button
              key={preset}
              type="button"
              aria-label={preset}
              aria-pressed={preset.toUpperCase() === value.toUpperCase()}
              onClick={() => field.setValue(preset)}
              className={cx('size-[22px] rounded-full', preset.toUpperCase() === value.toUpperCase() && 'ring-2 ring-offset-2 ring-offset-surface')}
              style={{ background: preset, '--tw-ring-color': preset } as React.CSSProperties}
            />
          ))}
        </div>
      )}
    </FieldFrame>
  );
}

/** Label::make(): a value among the fields that is only shown, never sent. */
export function LabelField({ node }: { node: FieldNode & { text?: string } }) {
  const id = useId();
  const value = useSnapshot((s) => getPath(s.page.state, node.path));
  const shown = node.text ?? (value === null || value === undefined || value === '' ? null : Array.isArray(value) ? value.join(', ') : String(value));

  return (
    <FieldFrame node={node} inputId={id} required={false}>
      <output id={id} className={cx('min-h-9 py-2 text-[14px] break-words', shown === null && 'text-fg-3')}>
        {shown ?? '—'}
      </output>
    </FieldFrame>
  );
}
