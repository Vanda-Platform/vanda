import type { ReactNode } from 'react';
import { Button, Label, ListBox, ListBoxItem, Popover, Select, SelectValue, Text, type PopoverProps } from 'react-aria-components';
import type { Option } from './types';
import { Icon } from './ui';

/** The list under a select: Select fields and the small selects below share it. */
export const popoverClass = 'min-w-(--trigger-width) max-w-[min(36rem,calc(100vw-2rem))] overflow-auto rounded-xl border border-line bg-surface p-1 shadow-pop outline-none';
export const optionClass =
  'flex min-h-8 cursor-pointer items-center justify-between gap-3 rounded-md px-2 py-1 text-[14px] text-fg outline-none data-[focused]:bg-surface-2 data-[selected]:font-medium data-[selected]:text-accent-fg';

/**
 * The text of an option in a list: its label (with the hint beside it) and the description as a second,
 * smaller grey line. Inside a ListBoxItem the two are its name and its description for screen readers.
 */
export function OptionText({ option }: { option: Pick<Option, 'label' | 'hint' | 'description'> }) {
  return (
    <span className="flex min-w-0 flex-col">
      <Text slot="label" className="truncate">
        {option.label}
        {option.hint && <span className="ml-2 font-normal text-fg-3">{option.hint}</span>}
      </Text>
      {option.description && (
        <Text slot="description" className="truncate text-[12.5px] leading-[1.35] font-normal text-fg-3">
          {option.description}
        </Text>
      )}
    </span>
  );
}

/** Focus ring of a select trigger drawn as a control (the frame of a text input). */
export const triggerFocusClass = 'outline-none data-[focus-visible]:border-accent data-[focus-visible]:ring-3 data-[focus-visible]:ring-ring';

export interface Choice {
  value: string;
  label: string;
}

/** '' is a choice like any other ("any" in a filter), but not a usable list key. */
const keyOf = (value: string) => `=${value}`;

/**
 * A small select outside a form: a table's filter, rows per page, sort. Its list is Vanda's, as in
 * Select fields, not the browser's: the same look in every browser and theme, with keyboard
 * navigation and type-to-select from React Aria.
 */
export function ChoiceSelect({
  label,
  labelClassName,
  value,
  choices,
  onChange,
  className,
  triggerClassName,
  children,
  autoFocus,
  placement,
}: {
  /** Shown when labelClassName is given; otherwise read out by screen readers only. */
  label: string;
  labelClassName?: string;
  value: string;
  choices: Choice[];
  onChange: (value: string) => void;
  className?: string;
  triggerClassName: string;
  /** What the trigger shows; by default the chosen label and a chevron. */
  children?: ReactNode;
  autoFocus?: boolean;
  placement?: PopoverProps['placement'];
}) {
  const items = choices.map((choice) => ({ id: keyOf(choice.value), ...choice }));

  return (
    <Select
      aria-label={labelClassName ? undefined : label}
      value={keyOf(value)}
      onChange={(key) => {
        if (typeof key === 'string' && key.startsWith('=')) onChange(key.slice(1));
      }}
      autoFocus={autoFocus}
      className={className}
    >
      {labelClassName && <Label className={labelClassName}>{label}</Label>}
      <Button className={triggerClassName}>
        {children ?? (
          <>
            <SelectValue className="truncate" />
            <Icon name="chevron-down" className="size-4 shrink-0 text-fg-3" />
          </>
        )}
      </Button>
      <Popover className={popoverClass} placement={placement}>
        <ListBox items={items} className="max-h-72 outline-none">
          {(choice) => (
            <ListBoxItem id={choice.id} textValue={choice.label} className={optionClass}>
              {({ isSelected }) => (
                <>
                  <span className="truncate">{choice.label}</span>
                  {isSelected && <Icon name="check" />}
                </>
              )}
            </ListBoxItem>
          )}
        </ListBox>
      </Popover>
    </Select>
  );
}
