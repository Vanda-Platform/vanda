import { createContext, useContext, useSyncExternalStore } from 'react';
import { evaluate } from './expr';
import { applyPatches, setPath, walk } from './patch';
import { act, react } from './transport';
import { t } from './i18n';
import type { ActionRef, FieldNode, Page, State, UiNode } from './types';

export interface Toast {
  id: number;
  message: string;
  tone: string;
}

export interface Snapshot {
  page: Page;
  errors: Record<string, string[]>;
  /** Node ids waiting for a server reaction. */
  pending: ReadonlySet<string>;
  /** Id of the button whose action is running. */
  running: string | null;
  toasts: Toast[];
}

const FLASH_KEY = 'vanda:toast';
const DISCRETE = new Set(['select', 'switch']);

/**
 * Client state of one page: values, errors, pending reactions, toasts.
 * Values change locally first; the server is asked only when a node depends
 * on the changed path, the field is live, or a button is pressed.
 */
export function createStore(initial: Page) {
  let snapshot: Snapshot = { page: initial, errors: {}, pending: new Set(), running: null, toasts: [] };
  const listeners = new Set<() => void>();
  let timer: ReturnType<typeof setTimeout> | undefined;
  let inflight: AbortController | undefined;
  let toastId = 0;

  const set = (patch: Partial<Snapshot>) => {
    snapshot = { ...snapshot, ...patch };
    listeners.forEach((listener) => listener());
  };

  const nodes = () => walk([...snapshot.page.commandBar, ...snapshot.page.layout]);

  const matches = (pattern: string, path: string) =>
    pattern === path || path.startsWith(`${pattern}.`) || (pattern.endsWith('*') && path.startsWith(pattern.slice(0, -1)));

  const withComputed = (state: State): State =>
    nodes()
      .filter((node): node is FieldNode => 'computed' in node && node.computed !== undefined)
      .reduce((current, field) => setPath(current, field.path, evaluate(field.computed!, current)), state);

  const pushToast = (message: string, tone = 'success') => {
    const id = ++toastId;
    set({ toasts: [...snapshot.toasts, { id, message, tone }] });
    setTimeout(() => set({ toasts: snapshot.toasts.filter((toast) => toast.id !== id) }), 5000);
  };

  async function runReaction() {
    inflight?.abort();
    const controller = new AbortController();
    inflight = controller;

    try {
      const { data } = await react(snapshot.page, snapshot.page.state, controller.signal);
      if (controller.signal.aborted) return;

      const errors = { ...snapshot.errors };
      for (const [path, messages] of Object.entries(data.errors)) {
        if (messages.length) errors[path] = messages;
        else delete errors[path];
      }

      set({ page: { ...applyPatches(snapshot.page, data.patches), snapshot: data.snapshot }, errors, pending: new Set() });
    } catch (error) {
      if (!controller.signal.aborted) {
        set({ pending: new Set() });
        pushToast(t('requestFailed'), 'danger');
        console.error(error);
      }
    }
  }

  return {
    subscribe(listener: () => void) {
      listeners.add(listener);
      return () => listeners.delete(listener);
    },

    get: () => snapshot,

    setValue(path: string, value: unknown) {
      const field = nodes().find((node): node is FieldNode => node.path === path);
      const dependents = nodes().filter((node) => node.dependsOn?.some((pattern) => matches(pattern, path)));
      const errors = { ...snapshot.errors };
      if (!field?.liveValidation) delete errors[path];

      set({ page: { ...snapshot.page, state: withComputed(setPath(snapshot.page.state, path, value)) }, errors });

      if (dependents.length === 0 && field?.live === undefined) return;

      const delay = field?.live ?? (field && DISCRETE.has(field.type) ? 0 : 300);
      set({ pending: new Set([...snapshot.pending, ...dependents.map((node) => node.id)]) });
      clearTimeout(timer);
      timer = setTimeout(runReaction, delay);
    },

    async runAction(id: string, action: ActionRef) {
      clearTimeout(timer);
      inflight?.abort();
      set({ running: id, pending: new Set() });

      try {
        const { status, data } = await act(snapshot.page, action);

        if (status === 422) {
          set({ errors: data.errors ?? {}, running: null, page: { ...snapshot.page, snapshot: data.snapshot ?? snapshot.page.snapshot } });
          pushToast(t('checkFields'), 'danger');
          const first = Object.keys(data.errors ?? {})[0];
          if (first) document.querySelector<HTMLElement>(`[data-vanda-path="${CSS.escape(first)}"] input, [data-vanda-path="${CSS.escape(first)}"] button`)?.focus();
          return;
        }

        if (data.redirect) {
          if (data.toast) sessionStorage.setItem(FLASH_KEY, JSON.stringify(data.toast));
          window.location.assign(data.redirect);
          return;
        }

        if (data.toast) pushToast(data.toast.message, data.toast.tone);
        set({
          running: null,
          errors: {},
          page: data.page ?? { ...snapshot.page, snapshot: data.snapshot ?? snapshot.page.snapshot },
        });
      } catch (error) {
        set({ running: null });
        pushToast(t('requestFailed'), 'danger');
        console.error(error);
      }
    },

    dismissToast(id: number) {
      set({ toasts: snapshot.toasts.filter((toast) => toast.id !== id) });
    },

    /** Shows a toast left by an action that redirected here. */
    showFlash() {
      const raw = sessionStorage.getItem(FLASH_KEY);
      if (!raw) return;
      sessionStorage.removeItem(FLASH_KEY);
      const toast = JSON.parse(raw) as { message: string; tone: string };
      pushToast(toast.message, toast.tone);
    },
  };
}

export type Store = ReturnType<typeof createStore>;

export const StoreContext = createContext<Store | null>(null);

export function useStore(): Store {
  const store = useContext(StoreContext);
  if (!store) throw new Error('Vanda components must be rendered inside <StoreContext.Provider>.');
  return store;
}

export function useSnapshot<T>(select: (snapshot: Snapshot) => T): T {
  const store = useStore();
  return useSyncExternalStore(store.subscribe, () => select(store.get()));
}

export function nodeAttributes(node: UiNode): Record<string, string | undefined> {
  return { 'data-vanda-node': node.id, 'data-vanda-type': node.type, 'data-vanda-source': node.source };
}
