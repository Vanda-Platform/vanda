import { useEffect, useId, useRef, useState, type ContextType } from 'react';
import { Tooltip, TooltipTriggerStateContext } from 'react-aria-components';
import { t } from '../i18n';
import { cx, Icon } from '../ui';

type TooltipState = NonNullable<ContextType<typeof TooltipTriggerStateContext>>;

/**
 * A field's hint (Field::help()) as an «i» with the text in a tooltip. It opens on hover (after a
 * moment), on keyboard focus and on a tap; it closes on Esc (before a modal around it hears it), on
 * leaving it, on blur and on a press elsewhere. The pointer may move onto the tooltip without it
 * closing (WCAG 1.4.13). The tooltip lives at the end of <body>, so no card or modal cuts it, and is a
 * React Aria top layer, so a modal does not make it inert. Screen readers get the text through
 * `describedBy` (the field's own hidden copy) or, without it, a hidden copy of its own.
 */
export function HelpTip({ text, label, describedBy, className }: { text: string; label?: string; describedBy?: string; className?: string }) {
  const own = useId();
  const button = useRef<HTMLButtonElement>(null);
  const tip = useRef<HTMLDivElement>(null);
  const timer = useRef<ReturnType<typeof setTimeout>>(undefined);
  // How the last press came: a mouse click keeps the tooltip open, a tap or Enter toggles it.
  const press = useRef<string | null>(null);
  const [open, setOpen] = useState(false);

  const show = (delay = 0) => {
    clearTimeout(timer.current);
    if (delay > 0) timer.current = setTimeout(() => setOpen(true), delay);
    else setOpen(true);
  };
  const hide = (delay = 0) => {
    clearTimeout(timer.current);
    if (delay > 0) timer.current = setTimeout(() => setOpen(false), delay);
    else setOpen(false);
  };

  useEffect(() => () => clearTimeout(timer.current), []);

  useEffect(() => {
    if (!open) return;
    const onKey = (event: KeyboardEvent) => {
      if (event.key !== 'Escape') return;
      // Captured on window, so a modal around the field stays open: Esc closes the tooltip first.
      event.stopPropagation();
      event.preventDefault();
      hide();
    };
    const onPress = (event: PointerEvent) => {
      const target = event.target as Node;
      if (!button.current?.contains(target) && !tip.current?.contains(target)) hide();
    };
    window.addEventListener('keydown', onKey, true);
    document.addEventListener('pointerdown', onPress, true);
    return () => {
      window.removeEventListener('keydown', onKey, true);
      document.removeEventListener('pointerdown', onPress, true);
    };
  }, [open]);

  // React Aria's tooltip reads its state from here: hovering the tooltip opens it, leaving closes it.
  const state: TooltipState = { isOpen: open, shouldSkipAnimation: false, open: () => show(), close: (immediate) => hide(immediate ? 0 : 150) };

  return (
    <>
      <button
        ref={button}
        type="button"
        aria-label={label ? t('hintFor', { label }) : t('hint')}
        aria-describedby={describedBy ?? own}
        data-open={open || undefined}
        onPointerEnter={(event) => event.pointerType === 'mouse' && show(200)}
        onPointerLeave={(event) => event.pointerType === 'mouse' && hide(150)}
        onPointerDown={(event) => {
          press.current = event.pointerType;
        }}
        onFocus={(event) => event.currentTarget.matches(':focus-visible') && show()}
        onBlur={() => hide()}
        onClick={() => {
          const pointer = press.current;
          press.current = null;
          if (pointer === 'mouse') show();
          else setOpen((current) => !current);
        }}
        // -my-1: the 24px target does not make the label row taller than its text.
        className={cx(
          '-my-1 grid size-6 shrink-0 cursor-help place-items-center rounded-full text-fg-3 outline-none hover:text-fg focus-visible:outline-2 focus-visible:outline-solid focus-visible:outline-accent data-[open]:text-fg',
          className,
        )}
      >
        <Icon name="info" className="size-4" />
      </button>
      {!describedBy && (
        <span id={own} className="sr-only">
          {text}
        </span>
      )}
      <TooltipTriggerStateContext.Provider value={state}>
        <Tooltip
          ref={tip}
          triggerRef={button}
          placement="top"
          offset={6}
          data-react-aria-top-layer="true"
          className="max-w-[min(20rem,calc(100vw-2rem))] rounded-lg bg-fg px-2.5 py-1.5 text-[12.5px] leading-snug font-normal break-words whitespace-pre-line text-surface shadow-pop data-[entering]:animate-[vanda-fade-in_120ms_ease-out] motion-reduce:animate-none"
        >
          {text}
        </Tooltip>
      </TooltipTriggerStateContext.Provider>
    </>
  );
}
