import { useEffect, useRef, useState } from 'react';
import { t } from './i18n';
import { cx, Icon } from './ui';

/** How long a copy button shows its check mark. */
const COPIED_FOR = 3000;

/** Puts text on the clipboard; falls back to execCommand where the Clipboard API is missing (plain http). */
export async function copyText(text: string): Promise<boolean> {
  try {
    await navigator.clipboard.writeText(text);
    return true;
  } catch {
    const area = document.createElement('textarea');
    area.value = text;
    area.style.position = 'fixed';
    area.style.opacity = '0';
    document.body.append(area);
    area.select();
    const done = document.execCommand('copy');
    area.remove();
    return done;
  }
}

/**
 * Every copy button in Vanda: `copied` is true for three seconds after a copy, so the button
 * shows a check mark instead of the copy icon, then turns back.
 */
export function useCopy() {
  const [copied, setCopied] = useState(false);
  const timer = useRef<ReturnType<typeof setTimeout> | undefined>(undefined);

  useEffect(() => () => clearTimeout(timer.current), []);

  const copy = async (text: string) => {
    if (!(await copyText(text))) return false;
    setCopied(true);
    clearTimeout(timer.current);
    timer.current = setTimeout(() => setCopied(false), COPIED_FOR);
    return true;
  };

  return { copied, copy };
}

/** A small icon button that copies a value and answers with a check mark for three seconds. */
export function CopyButton({ text, className }: { text: string; className?: string }) {
  const { copied, copy } = useCopy();

  return (
    <button
      type="button"
      aria-label={copied ? t('copied') : t('copy')}
      title={copied ? t('copied') : t('copy')}
      onClick={() => void copy(text)}
      className={cx('grid size-6 place-items-center rounded-md hover:bg-surface-2', copied ? 'text-success' : 'text-fg-3 hover:text-fg', className)}
    >
      <Icon name={copied ? 'check' : 'copy'} className="size-3.5" />
      <span aria-live="polite" className="sr-only">
        {copied ? t('copied') : ''}
      </span>
    </button>
  );
}
