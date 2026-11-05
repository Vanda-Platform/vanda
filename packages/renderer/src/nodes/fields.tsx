import { useId } from 'react';
import {
  Button as AriaButton,
  ListBox,
  ListBoxItem,
  Popover,
  Select as AriaSelect,
  SelectValue,
  Switch as AriaSwitch,
} from 'react-aria-components';
import { getPath, isTrue } from '../expr';
import { nodeAttributes, useSnapshot, useStore } from '../store';
import { t } from '../i18n';
import { cx, Icon, Spinner } from '../ui';
import type { FieldNode, Option } from '../types';

const controlClass = cx(
  'h-9 w-full rounded-lg border border-line-strong bg-surface px-2.5 text-[14px] text-fg shadow-sm transition',
  'placeholder:text-fg-3 focus:outline-none focus:border-accent focus:ring-3 focus:ring-ring',
  'disabled:cursor-not-allowed disabled:bg-surface-2 disabled:text-fg-3 disabled:shadow-none',
);

function useField(node: FieldNode) {
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

function FieldFrame({
  node,
  inputId,
  required,
  error,
  children,
  counter,
}: {
  node: FieldNode;
  inputId: string;
  required: boolean;
  error?: string;
  children: React.ReactNode;
  counter?: string;
}) {
  return (
    <div className="flex min-w-0 flex-col gap-1.5" data-vanda-path={node.path} {...nodeAttributes(node)}>
      {node.label && (
        <label htmlFor={inputId} className="flex items-center gap-1 text-[13px] font-medium text-fg">
          {node.label}
          {required && <span className="text-danger-fg" aria-hidden="true">*</span>}
        </label>
      )}
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
    </div>
  );
}

export function InputField({ node }: { node: FieldNode & { inputType?: string; prefix?: string; suffix?: string; maxLength?: number } }) {
  const id = useId();
  const field = useField(node);
  const input = (
    <input
      id={id}
      type={node.inputType ?? 'text'}
      value={field.value === null || field.value === undefined ? '' : String(field.value)}
      onChange={(event) => field.setValue(event.target.value)}
      placeholder={node.placeholder}
      maxLength={node.maxLength}
      disabled={field.disabled}
      readOnly={node.readonly}
      required={field.required}
      aria-invalid={field.error ? true : undefined}
      aria-describedby={field.error ? `${id}-error` : node.help ? `${id}-help` : undefined}
      className={cx(
        controlClass,
        field.error && 'border-danger focus:border-danger focus:ring-danger/20',
        node.prefix && 'rounded-l-none',
        node.suffix && 'rounded-r-none',
      )}
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

export function TextAreaField({ node }: { node: FieldNode & { rows?: number; maxLength?: number } }) {
  const id = useId();
  const field = useField(node);
  const text = field.value === null || field.value === undefined ? '' : String(field.value);

  return (
    <FieldFrame
      node={node}
      inputId={id}
      required={field.required}
      error={field.error}
      counter={node.maxLength ? `${text.length} / ${node.maxLength}` : undefined}
    >
      <textarea
        id={id}
        rows={node.rows ?? 4}
        value={text}
        onChange={(event) => field.setValue(event.target.value)}
        placeholder={node.placeholder}
        maxLength={node.maxLength}
        disabled={field.disabled}
        readOnly={node.readonly}
        aria-invalid={field.error ? true : undefined}
        className={cx(controlClass, 'h-auto resize-y py-2 leading-relaxed', field.error && 'border-danger')}
      />
    </FieldFrame>
  );
}

export function SelectField({ node }: { node: FieldNode & { options?: Option[] } }) {
  const id = useId();
  const field = useField(node);
  const options = node.options ?? [];
  const selected = options.find((option) => String(option.value) === String(field.value ?? ''));

  return (
    <FieldFrame node={node} inputId={id} required={field.required} error={field.error}>
      <AriaSelect
        id={id}
        aria-label={node.label ?? node.path}
        selectedKey={selected ? String(selected.value) : null}
        onSelectionChange={(key) => field.setValue(options.find((option) => String(option.value) === String(key))?.value ?? null)}
        isDisabled={field.disabled}
        isInvalid={Boolean(field.error)}
        placeholder={node.placeholder ?? t('choose')}
      >
        <AriaButton
          className={cx(
            controlClass,
            'flex items-center justify-between gap-2 text-left data-[focus-visible]:border-accent data-[focus-visible]:ring-3 data-[focus-visible]:ring-ring',
            field.error && 'border-danger',
          )}
        >
          <SelectValue className="truncate data-[placeholder]:text-fg-3" />
          <span className="flex items-center gap-2 text-fg-3">
            {field.pending && <Spinner className="size-3" />}
            <Icon name="chevron-down" />
          </span>
        </AriaButton>
        <Popover className="min-w-(--trigger-width) overflow-auto rounded-xl border border-line bg-surface p-1 shadow-pop outline-none">
          <ListBox items={options.map((option) => ({ id: String(option.value), ...option }))} className="max-h-72 outline-none">
            {(option) => (
              <ListBoxItem
                id={option.id}
                textValue={option.label}
                className="flex h-8 cursor-pointer items-center justify-between gap-3 rounded-md px-2 text-[14px] text-fg outline-none data-[focused]:bg-surface-2 data-[selected]:font-medium data-[selected]:text-accent-fg"
              >
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
      </AriaSelect>
    </FieldFrame>
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
