import { describe, expect, it } from 'vitest';
import { formatHotkey, matchesHotkey, parseHotkey } from './hotkeys';

const press = (key: string, code: string, modifiers: Partial<Record<'metaKey' | 'ctrlKey' | 'altKey' | 'shiftKey', boolean>> = {}) => ({
  key,
  code,
  metaKey: false,
  ctrlKey: false,
  altKey: false,
  shiftKey: false,
  ...modifiers,
});

describe('hotkeys', () => {
  it('take ⌘ for mod on a Mac and Ctrl elsewhere', () => {
    expect(matchesHotkey(press('s', 'KeyS', { metaKey: true }), 'mod+s', true)).toBe(true);
    expect(matchesHotkey(press('s', 'KeyS', { ctrlKey: true }), 'mod+s', true)).toBe(false);
    expect(matchesHotkey(press('s', 'KeyS', { ctrlKey: true }), 'mod+s', false)).toBe(true);
    expect(matchesHotkey(press('s', 'KeyS', { metaKey: true }), 'mod+s', false)).toBe(false);
  });

  it('find the key on a Russian layout by its place', () => {
    // ⌘S with the Russian layout on types «ы».
    expect(matchesHotkey(press('ы', 'KeyS', { metaKey: true }), 'mod+s', true)).toBe(true);
    expect(matchesHotkey(press('Ы', 'KeyS', { ctrlKey: true, shiftKey: true }), 'mod+shift+s', false)).toBe(true);
    expect(matchesHotkey(press('л', 'KeyK', { metaKey: true }), 'mod+k', true)).toBe(true);
  });

  it('trust the character on a Latin layout that moves keys', () => {
    // Dvorak: the key in the place of QWERTY's B types "x".
    expect(matchesHotkey(press('x', 'KeyB', { metaKey: true }), 'mod+b', true)).toBe(false);
    expect(matchesHotkey(press('x', 'KeyB', { metaKey: true }), 'mod+x', true)).toBe(true);
  });

  it('want exactly the modifiers named', () => {
    expect(matchesHotkey(press('s', 'KeyS', { metaKey: true }), 's', true)).toBe(false);
    expect(matchesHotkey(press('s', 'KeyS'), 'mod+s', true)).toBe(false);
    expect(matchesHotkey(press('S', 'KeyS', { metaKey: true, shiftKey: true }), 'mod+s', true)).toBe(false);
    expect(matchesHotkey(press('s', 'KeyS', { metaKey: true, altKey: true }), 'mod+s', true)).toBe(false);
    expect(matchesHotkey(press('ß', 'KeyS', { metaKey: true, altKey: true }), 'mod+alt+s', true)).toBe(true);
  });

  it('leave Shift to signs that need it', () => {
    // "?" is Shift and "/" on a US keyboard, "/" is Shift and 7 on a German one.
    expect(matchesHotkey(press('?', 'Slash', { shiftKey: true }), '?', true)).toBe(true);
    expect(matchesHotkey(press('/', 'Digit7', { shiftKey: true }), '/', true)).toBe(true);
    expect(matchesHotkey(press('/', 'Slash'), '/', true)).toBe(true);
  });

  it('know keys by name', () => {
    expect(matchesHotkey(press('Enter', 'Enter', { metaKey: true }), 'mod+enter', true)).toBe(true);
    expect(matchesHotkey(press('Escape', 'Escape'), 'esc', true)).toBe(true);
    expect(matchesHotkey(press('ArrowUp', 'ArrowUp', { altKey: true }), 'alt+up', true)).toBe(true);
    expect(parseHotkey('Mod+Shift+S')).toEqual({ key: 's', mod: true, ctrl: false, alt: false, shift: true });
  });

  it('are written as each system writes them', () => {
    expect(formatHotkey('mod+s', true)).toBe('⌘S');
    expect(formatHotkey('mod+shift+s', true)).toBe('⇧⌘S');
    expect(formatHotkey('mod+shift+s', false)).toBe('Ctrl+Shift+S');
    expect(formatHotkey('mod+enter', true)).toBe('⌘↵');
    expect(formatHotkey('alt+arrowup', false)).toBe('Alt+↑');
    expect(formatHotkey('/', false)).toBe('/');
  });
});
