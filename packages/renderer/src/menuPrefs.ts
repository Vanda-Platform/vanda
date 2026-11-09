import { useSyncExternalStore } from 'react';

/**
 * What the user keeps for the panel in this browser: the menu's folded groups and favourite
 * items (lists), the rail and the density (choices). Every component reading one sees a change at once (starring in the phone's menu sheet
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

/** Stored choices: null until the user makes one. */
const values = new Map<string, string | null>();

function readValue(key: string): string | null {
  if (values.has(key)) return values.get(key) ?? null;
  let value: string | null = null;
  try {
    value = window.localStorage.getItem(key);
  } catch {
    value = null;
  }
  values.set(key, value);
  return value;
}

/** A stored choice of the user (null when there is none) and a way to change or forget it. */
export function useStoredValue(key: string): [string | null, (value: string | null) => void] {
  const value = useSyncExternalStore(subscribe, () => readValue(key), () => null);
  const set = (next: string | null) => {
    values.set(key, next);
    try {
      if (next === null) window.localStorage.removeItem(key);
      else window.localStorage.setItem(key, next);
    } catch {
      // Kept for this page only.
    }
    listeners.forEach((listener) => listener());
  };
  return [value, set];
}

export const COLLAPSED_GROUPS = 'vanda:menu:collapsed';
export const FAVORITES = 'vanda:menu:favorites';
/** '1' the menu is a rail of icons, '0' it is open; none: a rail below 1280px. */
export const MENU_RAIL = 'vanda:menu:rail';
/** 'compact' or 'comfortable' when the user picked a density other than the panel's. */
export const DENSITY = 'vanda:density';
