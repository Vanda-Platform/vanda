import { useSyncExternalStore } from 'react';

/**
 * Lists the user keeps for the menu in this browser: groups folded away and favourite items.
 * Every component reading a list sees a change at once (starring in the phone's menu sheet
 * updates its tab bar); storage that throws (a private window) just forgets.
 */
const cache = new Map<string, string[]>();
const listeners = new Set<() => void>();

function read(key: string): string[] {
  const known = cache.get(key);
  if (known) return known;
  let list: string[] = [];
  try {
    const parsed: unknown = JSON.parse(window.localStorage.getItem(key) ?? '[]');
    if (Array.isArray(parsed)) list = parsed.filter((item): item is string => typeof item === 'string');
  } catch {
    list = [];
  }
  cache.set(key, list);
  return list;
}

function write(key: string, list: string[]) {
  cache.set(key, list);
  try {
    window.localStorage.setItem(key, JSON.stringify(list));
  } catch {
    // Kept for this page only.
  }
  listeners.forEach((listener) => listener());
}

const subscribe = (listener: () => void) => {
  listeners.add(listener);
  return () => listeners.delete(listener);
};

/** A stored list of strings and a way to add or take away one of them. */
export function useStoredList(key: string): [string[], (item: string) => void] {
  const list = useSyncExternalStore(subscribe, () => read(key), () => []);
  const toggle = (item: string) => {
    const current = read(key);
    write(key, current.includes(item) ? current.filter((entry) => entry !== item) : [...current, item]);
  };
  return [list, toggle];
}

export const COLLAPSED_GROUPS = 'vanda:menu:collapsed';
export const FAVORITES = 'vanda:menu:favorites';
