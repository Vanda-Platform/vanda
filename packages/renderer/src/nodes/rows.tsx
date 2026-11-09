import { Link, router } from '@inertiajs/react';
import type { MouseEvent, ReactNode } from 'react';
import { isInternal } from '../store';
import { cx } from '../ui';
import type { UiNode } from '../types';
import { NodeView } from './NodeView';

/** The plain text of a cell (a text, a badge, a persona's name), for labels and search. */
export const cellText = (cell: UiNode | undefined): string => String(cell?.text ?? cell?.label ?? cell?.name ?? '');

/**
 * Opens a row's link: in a new tab with Cmd/Ctrl, Shift or the middle button (as a link would),
 * else through Inertia (a panel page) or the browser (any other address).
 */
export function openLink(href: string, event?: { metaKey: boolean; ctrlKey: boolean; shiftKey: boolean; button?: number }) {
  if (event && (event.metaKey || event.ctrlKey || event.shiftKey || event.button === 1)) window.open(href, '_blank', 'noopener');
  else if (isInternal(href)) router.visit(href);
  else window.location.assign(href);
}

/**
 * Clicks anywhere on a row open its link, except on its own controls and while text is being
 * selected. The middle button opens it in a new tab (onAuxClick).
 */
export function rowClickHandlers(href: string | undefined) {
  if (!href) return {};
  const handle = (event: MouseEvent<HTMLElement>) => {
    if ((event.target as HTMLElement).closest('a,button,input,label,select,textarea,[role="menu"],[role="dialog"]')) return;
    if (window.getSelection()?.toString()) return;
    if (event.type === 'auxclick' && event.button !== 1) return;
    openLink(href, event);
  };
  return { onClick: handle, onAuxClick: handle };
}

/** A link to a panel page through Inertia, any other address as a plain link. */
export function AnyLink({ href, className, tabIndex, children }: { href: string; className?: string; tabIndex?: number; children: ReactNode }) {
  return isInternal(href) ? (
    <Link href={href} className={className} tabIndex={tabIndex}>
      {children}
    </Link>
  ) : (
    <a href={href} className={className} tabIndex={tabIndex}>
      {children}
    </a>
  );
}

const nameLink = 'font-medium text-fg underline-offset-4 hover:underline';

/**
 * The first cell of a row with a link: its name is the link itself, so the keyboard reaches it,
 * Cmd/Ctrl-click opens it in a new tab and a screen reader lists it among the links. A cell
 * that is not a text or a persona keeps its look and gets an «Открыть» link that shows on focus.
 */
export function RowLinkCell({ cell, href, tabIndex, openLabel }: { cell: UiNode; href: string; tabIndex?: number; openLabel: string }) {
  if (cell.type === 'text') {
    return (
      <AnyLink href={href} tabIndex={tabIndex} className={nameLink}>
        {String(cell.text ?? '')}
      </AnyLink>
    );
  }
  // In a tree the row itself is the link (Enter); a persona keeps its plain name there.
  if (cell.type === 'persona') return <NodeView node={tabIndex === -1 ? cell : { ...cell, href }} compact />;

  return (
    <span className="inline-flex items-center gap-2">
      <NodeView node={cell} compact />
      <AnyLink href={href} tabIndex={tabIndex} className={cx('sr-only focus-visible:not-sr-only', nameLink)}>
        {openLabel}
      </AnyLink>
    </span>
  );
}
