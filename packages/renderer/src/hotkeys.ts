import { useEffect, useRef, type RefObject } from 'react';

/**
 * Keyboard shortcuts of the panel: Button::hotkey('mod+s'), the palette's mod+k, a table's «/».
 *
 * One listener on window, in the capture phase: it hears the key before any field does, so a combobox
 * or a date field that stops the event (React Aria stops the keys it handles) cannot let the browser's
 * own shortcut through (⌘S «Save page as…»). A key is matched by the character it types and, when that
 * is not a Latin one (⌘S on a Russian layout types «ы»), by the physical key. Modifiers must be exactly
 * the ones named: "s" never fires on ⌘S, "mod+s" never on ⌘⇧S.
 *
 * While a modal is open the page behind it is inert: only a shortcut drawn inside the modal runs, and
 * the page's own is still kept from the browser. A key without a modifier ("/", "n") never fires while
 * the user types in a field.
 */

export interface Hotkey {
  key: string;
  mod: boolean;
  ctrl: boolean;
  alt: boolean;
  shift: boolean;
}

const isMacPlatform = () => typeof navigator !== 'undefined' && /Mac|iPhone|iPad/.test(navigator.platform);

const ALIASES: Record<string, string> = {
  esc: 'escape',
  return: 'enter',
  '↵': 'enter',
  up: 'arrowup',
  down: 'arrowdown',
  left: 'arrowleft',
  right: 'arrowright',
  del: 'delete',
  spacebar: 'space',
  ' ': 'space',
};

/** "mod+shift+s" → its parts. "mod" is ⌘ on macOS and Ctrl elsewhere. */
export function parseHotkey(hotkey: string): Hotkey {
  // "mod++" is mod and the plus key.
  const parts = hotkey.toLowerCase().replace(/\+\+$/, '+plus').split('+').map((part) => part.trim());
  const raw = parts[parts.length - 1] ?? '';
  const key = raw === 'plus' ? '+' : (ALIASES[raw] ?? raw);
  return { key, mod: parts.includes('mod'), ctrl: parts.includes('ctrl'), alt: parts.includes('alt'), shift: parts.includes('shift') };
}

/** The physical keys of a US layout, for keys whose character is not Latin on the user's layout. */
const CODES: Record<string, string> = {
  '/': 'Slash',
  '\\': 'Backslash',
  ',': 'Comma',
  '.': 'Period',
  ';': 'Semicolon',
  "'": 'Quote',
  '[': 'BracketLeft',
  ']': 'BracketRight',
  '-': 'Minus',
  '=': 'Equal',
  '`': 'Backquote',
};

function codeOf(key: string): string | null {
  if (/^[a-z]$/.test(key)) return `Key${key.toUpperCase()}`;
  if (/^[0-9]$/.test(key)) return `Digit${key}`;
  return CODES[key] ?? null;
}

/** A character a US keyboard types: what event.key holds on a Latin layout. */
const latin = (key: string) => key.length === 1 && key.charCodeAt(0) < 128;

export type KeyEventLike = Pick<KeyboardEvent, 'key' | 'code' | 'metaKey' | 'ctrlKey' | 'altKey' | 'shiftKey'>;

export function matchesHotkey(event: KeyEventLike, hotkey: string | Hotkey, mac = isMacPlatform()): boolean {
  const wanted = typeof hotkey === 'string' ? parseHotkey(hotkey) : hotkey;
  if (!event.key) return false;

  const meta = wanted.mod && mac;
  const ctrl = wanted.ctrl || (wanted.mod && !mac);
  if (event.metaKey !== meta || event.ctrlKey !== ctrl || event.altKey !== wanted.alt) return false;

  const pressed = event.key === ' ' ? 'space' : event.key.toLowerCase();
  const named = wanted.key.length > 1;
  // A letter or a digit compares Shift too; a sign like "/" or "?" already says whether Shift was down.
  const sign = !named && !/^[a-z0-9]$/.test(wanted.key);
  if (!sign && event.shiftKey !== wanted.shift) return false;

  if (named) return pressed === wanted.key;
  if (pressed === wanted.key) return true;
  // Option on a Mac and non-Latin layouts type other characters: then the physical key decides.
  if (latin(pressed) && !event.altKey) return false;
  const code = codeOf(wanted.key);
  return code !== null && event.code === code;
}

const GLYPHS: Record<string, string> = {
  enter: '↵',
  escape: 'Esc',
  backspace: '⌫',
  delete: 'Del',
  tab: 'Tab',
  space: 'Space',
  arrowup: '↑',
  arrowdown: '↓',
  arrowleft: '←',
  arrowright: '→',
};

/** "mod+shift+s" → "⌘⇧S" on macOS, "Ctrl+Shift+S" elsewhere. */
export function formatHotkey(hotkey: string, mac = isMacPlatform()): string {
  const { key, mod, ctrl, alt, shift } = parseHotkey(hotkey);
  const name = GLYPHS[key] ?? (key.length === 1 ? key.toUpperCase() : key.charAt(0).toUpperCase() + key.slice(1));
  if (mac) return `${ctrl ? '⌃' : ''}${alt ? '⌥' : ''}${shift ? '⇧' : ''}${mod ? '⌘' : ''}${name}`;
  return [...(mod || ctrl ? ['Ctrl'] : []), ...(alt ? ['Alt'] : []), ...(shift ? ['Shift'] : []), name].join('+');
}

/** Where the user types: a key without a modifier belongs to the text there. */
function editable(target: EventTarget | null): boolean {
  if (!(target instanceof Element)) return false;
  return Boolean(target.closest('input, textarea, select, [contenteditable=""], [contenteditable="true"], [role="textbox"], .cm-editor'));
}

/**
 * Drawn and reachable: not display: none, not in a hidden tab, not behind an open dialog. An open list
 * (a select, a menu, a combobox) also makes the page inert or hides it from screen readers, but the
 * user still means the page's shortcut.
 */
function live(element: Element): boolean {
  if (!element.isConnected || element.getClientRects().length === 0) return false;
  if (!element.closest('[inert]')) return true;
  if (element.closest('[role="tabpanel"][inert]')) return false;
  const dialogs = [...document.querySelectorAll('[role="dialog"], [role="alertdialog"]')].filter((dialog) => dialog.getClientRects().length > 0);
  return dialogs.every((dialog) => dialog.contains(element));
}

interface Entry {
  hotkey: Hotkey;
  run: () => void;
  /** The control the shortcut presses: it must be live for the shortcut to run. None: the whole page's. */
  element?: RefObject<Element | null>;
  order: number;
}

const entries = new Set<Entry>();
let counter = 0;

export function onHotkeyDown(event: KeyboardEvent): void {
  if (event.isComposing || entries.size === 0) return;
  const matching = [...entries].filter((entry) => matchesHotkey(event, entry.hotkey));
  if (matching.length === 0) return;

  const plain = !matching[0]!.hotkey.mod && !matching[0]!.hotkey.ctrl && !matching[0]!.hotkey.alt;
  if (plain && editable(event.target)) return;

  // The page has this shortcut: the browser's own never runs, even when the page's control is out of reach now.
  event.preventDefault();
  if (event.repeat) return;

  const reachable = matching.filter((entry) => !entry.element || (entry.element.current !== null && live(entry.element.current)));
  // The latest drawn wins: a modal's button over the page's.
  const chosen = reachable.sort((a, b) => b.order - a.order).find((entry) => entry.element) ?? reachable[0];
  if (!chosen) return;
  event.stopPropagation();
  chosen.run();
}

if (typeof window !== 'undefined') window.addEventListener('keydown', onHotkeyDown, true);

/**
 * Runs `run` on the shortcut while the component is mounted. With `element`, only while that control
 * is drawn and reachable (not behind a modal, not in a hidden tab).
 *
 *     const button = useRef<HTMLButtonElement>(null);
 *     useHotkey(node.hotkey, save, button);
 */
export function useHotkey(hotkey: string | null | undefined, run: () => void, element?: RefObject<Element | null>): void {
  const latest = useRef(run);
  latest.current = run;

  useEffect(() => {
    if (!hotkey) return;
    const entry: Entry = { hotkey: parseHotkey(hotkey), run: () => latest.current(), element, order: ++counter };
    entries.add(entry);
    return () => {
      entries.delete(entry);
    };
  }, [hotkey, element]);
}
