import { parseDate, parseDateTime, type CalendarDate, type CalendarDateTime } from '@internationalized/date';
import { useId } from 'react';
import {
  Button as AriaButton,
  Calendar,
  CalendarCell,
  CalendarGrid,
  DateInput,
  DatePicker,
  DateRangePicker,
  DateSegment,
  Dialog,
  Group,
  Heading,
  Popover,
  RangeCalendar,
} from 'react-aria-components';
import { controlClass, cx, Icon, invalidClass } from '../ui';
import type { FieldNode } from '../types';
import { FieldFrame, useField } from './fields';

// Date pickers live in their own chunk: the calendar and date maths load only on screens that use them.

const cellClass =
  'grid h-8 w-9 cursor-pointer place-items-center rounded-lg text-[13.5px] tabular-nums outline-none data-[outside-month]:text-fg-3 data-[hovered]:bg-surface-2 data-[focus-visible]:ring-3 data-[focus-visible]:ring-ring data-[selected]:bg-accent data-[selected]:font-semibold data-[selected]:text-white data-[disabled]:opacity-40';

function CalendarHeader() {
  return (
    <header className="mb-2 flex items-center justify-between">
      <AriaButton slot="previous" className="grid size-7 place-items-center rounded-md text-fg-2 hover:bg-surface-2">
        <Icon name="chevron-left" />
      </AriaButton>
      <Heading className="text-[14px] font-semibold capitalize" />
      <AriaButton slot="next" className="grid size-7 place-items-center rounded-md text-fg-2 hover:bg-surface-2">
        <Icon name="chevron-right" />
      </AriaButton>
    </header>
  );
}

function toDate(value: unknown, withTime: boolean): CalendarDate | CalendarDateTime | null {
  if (typeof value !== 'string' || value === '') return null;
  try {
    return withTime ? parseDateTime(value.slice(0, 19)) : parseDate(value.slice(0, 10));
  } catch {
    return null;
  }
}

function DateSegments() {
  return (
    <DateInput className="flex flex-1 items-center">
      {(segment) => (
        <DateSegment
          segment={segment}
          className="rounded px-0.5 tabular-nums outline-none data-[focused]:bg-accent-soft data-[focused]:text-accent-fg data-[placeholder]:text-fg-3 data-[type=literal]:px-0"
        />
      )}
    </DateInput>
  );
}

export function DateField({ node }: { node: FieldNode & { withTime?: boolean; min?: string; max?: string } }) {
  const id = useId();
  const field = useField(node);
  const withTime = Boolean(node.withTime);

  return (
    <FieldFrame node={node} inputId={id} required={field.required} error={field.error}>
      <DatePicker
        id={id}
        aria-label={node.label ?? node.path}
        value={toDate(field.value, withTime)}
        onChange={(value) => field.setValue(value ? value.toString().slice(0, withTime ? 16 : 10) : null)}
        granularity={withTime ? 'minute' : 'day'}
        hourCycle={24}
        minValue={node.min ? parseDate(node.min) : undefined}
        maxValue={node.max ? parseDate(node.max) : undefined}
        isDisabled={field.disabled}
        isInvalid={Boolean(field.error)}
      >
        <Group className={cx(controlClass, 'flex items-center gap-1 pr-1', field.error && invalidClass)}>
          <DateSegments />
          <AriaButton className="grid size-7 place-items-center rounded-md text-fg-3 hover:bg-surface-2 hover:text-fg">
            <Icon name="calendar" />
          </AriaButton>
        </Group>
        <Popover className="rounded-xl border border-line bg-surface p-3 shadow-pop outline-none">
          <Dialog className="outline-none">
            <Calendar>
              <CalendarHeader />
              <CalendarGrid>{(date) => <CalendarCell date={date} className={cellClass} />}</CalendarGrid>
            </Calendar>
          </Dialog>
        </Popover>
      </DatePicker>
    </FieldFrame>
  );
}

export function DateRangeField({ node }: { node: FieldNode }) {
  const id = useId();
  const field = useField(node);
  const raw = (field.value ?? {}) as { start?: string; end?: string };
  const start = toDate(raw.start, false) as CalendarDate | null;
  const end = toDate(raw.end, false) as CalendarDate | null;

  return (
    <FieldFrame node={node} inputId={id} required={field.required} error={field.error}>
      <DateRangePicker
        id={id}
        aria-label={node.label ?? node.path}
        value={start && end ? { start, end } : null}
        onChange={(range) => field.setValue(range ? { start: range.start.toString(), end: range.end.toString() } : null)}
        isDisabled={field.disabled}
      >
        <Group className={cx(controlClass, 'flex items-center gap-1 pr-1')}>
          <DateInput slot="start" className="flex items-center">
            {(segment) => <DateSegment segment={segment} className="rounded px-0.5 tabular-nums outline-none data-[focused]:bg-accent-soft data-[placeholder]:text-fg-3" />}
          </DateInput>
          <span aria-hidden="true" className="px-1 text-fg-3">–</span>
          <DateInput slot="end" className="flex flex-1 items-center">
            {(segment) => <DateSegment segment={segment} className="rounded px-0.5 tabular-nums outline-none data-[focused]:bg-accent-soft data-[placeholder]:text-fg-3" />}
          </DateInput>
          <AriaButton className="grid size-7 place-items-center rounded-md text-fg-3 hover:bg-surface-2 hover:text-fg">
            <Icon name="calendar" />
          </AriaButton>
        </Group>
        <Popover className="rounded-xl border border-line bg-surface p-3 shadow-pop outline-none">
          <Dialog className="outline-none">
            <RangeCalendar>
              <CalendarHeader />
              <CalendarGrid>{(date) => <CalendarCell date={date} className={cx(cellClass, 'data-[selected]:rounded-none data-[selection-start]:rounded-l-lg data-[selection-end]:rounded-r-lg')} />}</CalendarGrid>
            </RangeCalendar>
          </Dialog>
        </Popover>
      </DateRangePicker>
    </FieldFrame>
  );
}
