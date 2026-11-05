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
import { getPath, isTrue } from '../expr';
import { t } from '../i18n';
import { nodeAttributes, useSnapshot, useStore } from '../store';
import { controlClass, cx, Icon, invalidClass, Spinner } from '../ui';
import type { FieldNode, Option } from '../types';

export function useField(node: FieldNode) {
  const store = useStore();
  const value = useSnapshot((s) => getPath(s.page.state, node.path));
  const errors = useSnapshot((s) => s.errors[node.path]);
  const state = useSnapshot((s) => s.page.state);
  const pending = useSnapshot((s) => s.pending.has(node.id));

  return {
    value,
    error: errors?.[0],
    pending,
    required: Boolean(node.required) || (node.requiredWhen !== undefined && isTrue(node.requiredWhen, state)),
    disabled: Boolean(node.disabled) || (node.disabledWhen !== undefined && isTrue(node.disabledWhen, state)) || node.computed !== undefined,
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
  return (
    <Root className="m-0 flex min-w-0 flex-col gap-1.5 border-0 p-0" data-vanda-path={node.path} {...nodeAttributes(node)}>
      {node.label &&
        (as === 'fieldset' ? (
          <legend className="mb-1.5 flex items-center gap-1 text-[13px] font-medium text-fg">
            {node.label}
            {required && <span className="text-danger-fg" aria-hidden="true">*</span>}
          </legend>
        ) : (
          <label htmlFor={inputId} className="flex items-center gap-1 text-[13px] font-medium text-fg">
            {node.label}
            {required && <span className="text-danger-fg" aria-hidden="true">*</span>}
          </label>
        ))}
      {children}
      {error ? (
        <span id={`${inputId}-error`} className="flex items-center gap-1.5 text-[12.5px] text-danger-fg">
          <Icon name="alert" className="size-3.5" />
          {error}
        </span>
      ) : (
        (node.help || counter) && (
          <span className="flex justify-between gap-2 text-[12.5px] text-fg-3">
            <span id={`${inputId}-help`}>{node.help}</span>
            {counter && <span className="tabular-nums">{counter}</span>}
          </span>
        )
      )}
    </Root>
  );
}

const text = (value: unknown) => (value === null || value === undefined ? '' : String(value));

export function InputField({ node }: { node: FieldNode & { inputType?: string; prefix?: string; suffix?: string; maxLength?: number } }) {
  const id = useId();
  const field = useField(node);
  const input = (
    <input
      id={id}
      type={node.inputType ?? 'text'}
      value={text(field.value)}
      onChange={(event) => field.setValue(event.target.value)}
      placeholder={node.placeholder}
      maxLength={node.maxLength}
      disabled={field.disabled}
      readOnly={node.readonly}
      required={field.required}
      aria-invalid={field.error ? true : undefined}
      aria-describedby={field.error ? `${id}-error` : node.help ? `${id}-help` : undefined}
      className={cx(controlClass, field.error && invalidClass, (node.prefix || node.suffix) && 'shadow-none', node.prefix && 'rounded-l-none', node.suffix && 'rounded-r-none')}
    />
  );

  return (
    <FieldFrame node={node} inputId={id} required={field.required} error={field.error}>
      {node.prefix || node.suffix ? (
        <div className="flex">
          {node.prefix && <span className="flex items-center rounded-l-lg border border-r-0 border-line-strong bg-surface-2 px-2.5 text-fg-2">{node.prefix}</span>}
          {input}
          {node.suffix && <span className="flex items-center rounded-r-lg border border-l-0 border-line-strong bg-surface-2 px-2.5 text-fg-2">{node.suffix}</span>}
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
          disabled={field.disabled}
          aria-invalid={field.error ? true : undefined}
          className={cx(controlClass, 'pr-10', field.error && invalidClass)}
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
        disabled={field.disabled}
        readOnly={node.readonly}
        aria-invalid={field.error ? true : undefined}
        className={cx(controlClass, 'h-auto resize-y py-2 leading-relaxed', field.error && invalidClass)}
      />
    </FieldFrame>
  );
}

const popoverClass = 'min-w-(--trigger-width) overflow-auto rounded-xl border border-line bg-surface p-1 shadow-pop outline-none';
const optionClass =
  'flex h-8 cursor-pointer items-center justify-between gap-3 rounded-md px-2 text-[14px] text-fg outline-none data-[focused]:bg-surface-2 data-[selected]:font-medium data-[selected]:text-accent-fg';

export function SelectField({ node }: { node: FieldNode & { options?: Option[]; multiple?: boolean } }) {
  const id = useId();
  const field = useField(node);
  const options = node.options ?? [];
  const byKey = (key: unknown) => options.find((option) => String(option.value) === String(key));
  const items = options.map((option) => ({ id: String(option.value), ...option }));
  const trigger = cx(
    controlClass,
    'flex items-center justify-between gap-2 text-left data-[focus-visible]:border-accent data-[focus-visible]:ring-3 data-[focus-visible]:ring-ring',
    field.error && invalidClass,
  );
  const list = (
    <Popover className={popoverClass}>
      <ListBox items={items} className="max-h-72 outline-none">
        {(option) => (
          <ListBoxItem id={option.id} textValue={option.label} className={optionClass}>
            {({ isSelected }) => (
              <>
                <span className="truncate">{option.label}</span>
                {isSelected && <Icon name="check" />}
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
          selectionMode="multiple"
          value={selected}
          onChange={(keys) => field.setValue(keys.map((key) => byKey(key)?.value ?? key))}
          isDisabled={field.disabled}
        >
          <AriaButton className={cx(trigger, 'h-auto min-h-9 py-1')}>
            <span className="flex flex-wrap gap-1.5">
              {selected.length === 0 && <span className="text-fg-3">{node.placeholder ?? t('choose')}</span>}
              {selected.map((key) => (
                <span key={key} className="inline-flex h-6 items-center rounded-md border border-line bg-surface-2 px-2 text-[13px] font-medium">
                  {byKey(key)?.label ?? key}
                </span>
              ))}
            </span>
            <Icon name="chevron-down" className="size-4 shrink-0 text-fg-3" />
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
        value={current ? String(current.value) : null}
        onChange={(key) => field.setValue(byKey(key)?.value ?? null)}
        isDisabled={field.disabled}
        isInvalid={Boolean(field.error)}
        placeholder={node.placeholder ?? t('choose')}
      >
        <AriaButton className={trigger}>
          <SelectValue className="truncate data-[placeholder]:text-fg-3" />
          <span className="flex items-center gap-2 text-fg-3">
            {field.pending && <Spinner className="size-3" />}
            <Icon name="chevron-down" />
          </span>
        </AriaButton>
        {list}
      </AriaSelect>
    </FieldFrame>
  );
}

const box =
  'grid size-4 shrink-0 place-items-center rounded-[4.5px] border border-line-strong bg-surface text-white shadow-sm transition group-data-[selected]:border-accent group-data-[selected]:bg-accent group-data-[focus-visible]:ring-3 group-data-[focus-visible]:ring-ring';

export function CheckboxField({ node }: { node: FieldNode }) {
  const field = useField(node);

  return (
    <div className="flex flex-col gap-1.5" data-vanda-path={node.path} {...nodeAttributes(node)}>
      <AriaCheckbox
        isSelected={Boolean(field.value)}
        onChange={(selected) => field.setValue(selected)}
        isDisabled={field.disabled}
        className="group flex cursor-pointer items-start gap-2.5 text-[14px] data-[disabled]:cursor-not-allowed data-[disabled]:opacity-60"
      >
        <span className={cx(box, 'mt-[3px]')}>
          <Icon name="check" className="size-3 opacity-0 group-data-[selected]:opacity-100" />
        </span>
        <span className="flex flex-col">
          <span>{node.label}</span>
          {node.help && <span className="text-[12.5px] text-fg-3">{node.help}</span>}
        </span>
      </AriaCheckbox>
      {field.error && <span className="text-[12.5px] text-danger-fg">{field.error}</span>}
    </div>
  );
}

export function SwitchField({ node }: { node: FieldNode }) {
  const field = useField(node);

  return (
    <div className="flex flex-col gap-1.5" data-vanda-path={node.path} {...nodeAttributes(node)}>
      <AriaSwitch
        isSelected={Boolean(field.value)}
        onChange={(selected) => field.setValue(selected)}
        isDisabled={field.disabled}
        className="group flex cursor-pointer items-start gap-2.5 text-[14px] data-[disabled]:cursor-not-allowed data-[disabled]:opacity-60"
      >
        <span className="mt-0.5 flex h-[18px] w-8 shrink-0 items-center rounded-full bg-line-strong px-0.5 transition-colors group-data-[selected]:bg-accent group-data-[focus-visible]:ring-3 group-data-[focus-visible]:ring-ring">
          <span className="size-3.5 rounded-full bg-white shadow transition-transform group-data-[selected]:translate-x-3.5" />
        </span>
        <span className="flex flex-col">
          <span className="font-medium">{node.label}</span>
          {node.help && <span className="text-[12.5px] text-fg-3">{node.help}</span>}
        </span>
      </AriaSwitch>
      {field.error && <span className="text-[12.5px] text-danger-fg">{field.error}</span>}
    </div>
  );
}

export function RadioField({ node }: { node: FieldNode & { options?: Option[]; inline?: boolean } }) {
  const field = useField(node);
  const options = node.options ?? [];

  return (
    <div className="flex flex-col gap-1.5" data-vanda-path={node.path} {...nodeAttributes(node)}>
      <RadioGroup
        aria-label={node.label ?? node.path}
        value={field.value === null || field.value === undefined ? null : String(field.value)}
        onChange={(key) => field.setValue(options.find((option) => String(option.value) === key)?.value ?? key)}
        isDisabled={field.disabled}
        orientation={node.inline ? 'horizontal' : 'vertical'}
        className="flex flex-col gap-2"
      >
        {node.label && <span className="text-[13px] font-medium">{node.label}</span>}
        <div className={cx('flex gap-2.5', node.inline ? 'flex-row flex-wrap gap-x-5' : 'flex-col')}>
          {options.map((option) => (
            <AriaRadio key={String(option.value)} value={String(option.value)} className="group flex cursor-pointer items-center gap-2.5 text-[14px]">
              <span className="size-4 rounded-full border border-line-strong bg-surface shadow-sm transition group-data-[selected]:border-[5px] group-data-[selected]:border-accent group-data-[focus-visible]:ring-3 group-data-[focus-visible]:ring-ring" />
              {option.label}
            </AriaRadio>
          ))}
        </div>
      </RadioGroup>
      {field.error && <span className="text-[12.5px] text-danger-fg">{field.error}</span>}
    </div>
  );
}

export function SegmentedField({ node }: { node: FieldNode & { options?: Option[] } }) {
  const field = useField(node);
  const options = node.options ?? [];

  return (
    <div className="flex flex-col gap-1.5" data-vanda-path={node.path} {...nodeAttributes(node)}>
      <RadioGroup
        aria-label={node.label ?? node.path}
        orientation="horizontal"
        value={field.value === null || field.value === undefined ? null : String(field.value)}
        onChange={(key) => field.setValue(options.find((option) => String(option.value) === key)?.value ?? key)}
        isDisabled={field.disabled}
        className="flex flex-col gap-1.5"
      >
        {node.label && <span className="text-[13px] font-medium">{node.label}</span>}
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
      {field.error && <span className="text-[12.5px] text-danger-fg">{field.error}</span>}
    </div>
  );
}

export function RangeField({ node }: { node: FieldNode & { min?: number; max?: number; step?: number; suffix?: string } }) {
  const field = useField(node);
  const min = node.min ?? 0;
  const max = node.max ?? 100;
  const value = typeof field.value === 'number' ? field.value : Number(field.value ?? min) || min;

  return (
    <div className="flex flex-col gap-2" data-vanda-path={node.path} {...nodeAttributes(node)}>
      <Slider value={value} onChange={(next) => field.setValue(next)} minValue={min} maxValue={max} step={node.step ?? 1} isDisabled={field.disabled} aria-label={node.label ?? node.path}>
        <div className="mb-2 flex justify-between text-[13px]">
          <span className="font-medium">{node.label}</span>
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
      {node.help && <span className="text-[12.5px] text-fg-3">{node.help}</span>}
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
            <span className="flex items-center rounded-l-lg border border-r-0 border-line-strong bg-surface-2 px-2.5 text-fg-2">{t(key)}</span>
            <input
              id={key === 'from' ? id : undefined}
              type="number"
              inputMode="decimal"
              value={text(value[key])}
              onChange={(event) => update(key, event.target.value)}
              disabled={field.disabled}
              aria-label={`${node.label ?? ''} ${t(key)}`}
              className={cx(controlClass, 'rounded-l-none tabular-nums', node.suffix && 'rounded-r-none')}
            />
            {node.suffix && <span className="flex items-center rounded-r-lg border border-l-0 border-line-strong bg-surface-2 px-2.5 text-fg-2">{node.suffix}</span>}
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
          className="h-9 w-10 shrink-0 cursor-pointer rounded-lg border border-line-strong bg-surface p-1"
        />
        <input id={id} value={text(field.value)} onChange={(event) => field.setValue(event.target.value)} disabled={field.disabled} className={cx(controlClass, 'font-mono text-[13.5px]')} />
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
