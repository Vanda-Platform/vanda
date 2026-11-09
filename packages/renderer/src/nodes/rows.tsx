import { Link } from '@inertiajs/react';
import type { MouseEvent, ReactNode } from 'react';
import { isInternal, useSnapshot } from '../store';
import { cx, Spinner } from '../ui';
import type { UiNode } from '../types';
import { openLink, useActivate, type ActivatableNode, type LinkEvent } from './actions';
import { NodeView } from './NodeView';

/** The plain text of a cell (a text, a badge, a persona's name), for labels and search. */
export const cellText = (cell: UiNode | undefined): string => String(cell?.text ?? cell?.label ?? cell?.name ?? '');

/**
 * What a click on a row opens: the address of Table::rowLink(), or the node of Table::rowAction()
 * built for the row (a modal toggle, a button or a link). A row has one of them at most.
 */
export interface RowOpens {
  link?: string;
  action?: ActivatableNode;
}

/** The address a row leads to, if it leads to a page: the middle button opens it in a new tab. */
const rowHref = (row: RowOpens): string | undefined => row.link ?? (row.action?.type === 'link' ? row.action.href : undefined);

/** Whether a click or Enter on the row does anything. */
export const opensSomething = (row: RowOpens): boolean => Boolean(row.link || row.action);

/**
 * Opens rows: `open(row, event)` follows the row's link or runs its action node (a modal, a button,
 * which asks first when it has ->confirm(), or a link). Render `dialog` once beside the rows.
 */
export function useRowOpener() {
  const { activate, dialog } = useActivate();
  const busy = useSnapshot((s) => s.running !== null);

  const open = (row: RowOpens, event?: LinkEvent) => {
    if (row.link) openLink(row.link, event);
    // One action at a time, as the buttons are disabled while one runs.
    else if (row.action && !busy) activate(row.action, event);
  };

  return { open, dialog };
}

/**
 * Clicks anywhere on a row open it, except on its own controls and while text is being selected.
 * The middle button opens a row that leads to a page in a new tab (onAuxClick).
 */
export function rowClickHandlers(row: RowOpens, open: (row: RowOpens, event: LinkEvent) => void) {
  if (!opensSomething(row)) return {};
  const handle = (event: MouseEvent<HTMLElement>) => {
    if ((event.target as HTMLElement).closest('a,button,input,label,select,textarea,[role="menu"],[role="dialog"]')) return;
    if (window.getSelection()?.toString()) return;
    if (event.type === 'auxclick' && (event.button !== 1 || !rowHref(row))) return;
    open(row, event);
  };
  return { onClick: handle, onAuxClick: handle };
}

/** A link to a panel page through Inertia, any other address as a plain link (`external`: in a new tab). */
export function AnyLink({ href, className, tabIndex, external, children }: { href: string; className?: string; tabIndex?: number; external?: boolean; children: ReactNode }) {
  return isInternal(href) && !external ? (
    <Link href={href} className={className} tabIndex={tabIndex}>
      {children}
    </Link>
  ) : (
    <a href={href} className={className} tabIndex={tabIndex} target={external ? '_blank' : undefined} rel={external ? 'noopener noreferrer' : undefined}>
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
export function RowLinkCell({ cell, href, tabIndex, openLabel, external }: { cell: UiNode; href: string; tabIndex?: number; openLabel: string; external?: boolean }) {
  if (cell.type === 'text') {
    return (
      <AnyLink href={href} tabIndex={tabIndex} external={external} className={nameLink}>
        {String(cell.text ?? '')}
      </AnyLink>
    );
  }
  // In a tree the row itself is the link (Enter); a persona keeps its plain name there.
  if (cell.type === 'persona') return <NodeView node={tabIndex === -1 || external ? cell : { ...cell, href }} compact />;

  return (
    <span className="inline-flex items-center gap-2">
      <NodeView node={cell} compact />
      <AnyLink href={href} tabIndex={tabIndex} external={external} className={cx('sr-only focus-visible:not-sr-only', nameLink)}>
        {openLabel}
      </AnyLink>
    </span>
  );
}

/**
 * The first cell of a row that opens something. With a link (rowLink() or a Link as the row's
 * action) it is RowLinkCell; with a modal toggle or a button the name is a button that does what a
 * click on the row does, so the keyboard reaches it and a screen reader hears what it opens. A
 * cell that is not a text keeps its look and gets a button named after the action that shows on
 * focus. In a tree (`tabIndex` -1) the row itself takes the focus and Enter.
 */
export function RowOpenCell({ cell, row, open, tabIndex, openLabel }: { cell: UiNode; row: RowOpens; open: (row: RowOpens, event: LinkEvent) => void; tabIndex?: number; openLabel: string }) {
  const action = row.action;
  const running = useSnapshot((s) => action !== undefined && s.running === action.id);

  if (row.link) return <RowLinkCell cell={cell} href={row.link} tabIndex={tabIndex} openLabel={openLabel} />;
  if (!action) return <NodeView node={cell} compact />;
  if (action.type === 'link' && action.href) return <RowLinkCell cell={cell} href={action.href} tabIndex={tabIndex} openLabel={action.label || openLabel} external={action.external} />;

  const spinner = running && <Spinner />;
  const button = (children: ReactNode, className: string) => (
    <button
      type="button"
      tabIndex={tabIndex}
      aria-haspopup={action.type === 'modaltoggle' ? 'dialog' : undefined}
      aria-busy={running || undefined}
      onClick={(event) => open(row, event)}
      className={className}
    >
      {children}
    </button>
  );

  if (cell.type === 'text') {
    // inline-block with max-w-full: a long name ends with «…» inside the column, as a link's would.
    return (
      <>
        {button(String(cell.text ?? ''), cx(nameLink, 'inline-block max-w-full cursor-pointer truncate text-left align-bottom'))}
        {spinner && <span className="ml-2 inline-flex align-middle">{spinner}</span>}
      </>
    );
  }

  return (
    <span className="inline-flex items-center gap-2">
      <NodeView node={cell} compact />
      {tabIndex !== -1 && button(action.label || openLabel, cx('sr-only focus-visible:not-sr-only', nameLink))}
      {spinner}
    </span>
  );
}
