import { router } from '@inertiajs/react';
import { createContext, useContext, useSyncExternalStore } from 'react';
import { evaluate, getPath } from './expr';
import { t } from './i18n';
import { applyPatches, setPath, walk } from './patch';
import { act, react } from './transport';
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
  /** Id of the open Layout::modal(). */
  modal: string | null;
  toasts: Toast[];
  /** State as last loaded or saved; fields that differ from it are unsaved changes. */
  baseline: State;
}

const FLASH_KEY = 'vanda:toast';
const DISCRETE = new Set(['select', 'switch', 'checkbox', 'radio', 'segmented', 'date', 'daterange', 'color', 'range']);

/**
 * Client state of one screen: values, errors, pending reactions, the open modal, toasts.
 * Values change locally first; the server is asked only when a node depends on the
 * changed path, the field is live, or a button is pressed.
 */
export function createStore(initial: Page) {
  let snapshot: Snapshot = { page: initial, errors: {}, pending: new Set(), running: null, modal: null, toasts: [], baseline: initial.state };
  const listeners = new Set<() => void>();
  let timer: ReturnType<typeof setTimeout> | undefined;
  let inflight: AbortController | undefined;
  let toastId = 0;
  /** The page object we pushed into Inertia ourselves; its echo must not reset the store. */
  let synced: Page | null = initial;

  const set = (patch: Partial<Snapshot>) => {
    snapshot = { ...snapshot, ...patch };
    listeners.forEach((listener) => listener());
  };

  const nodes = () => walk([...snapshot.page.commandBar, ...snapshot.page.layout]);

  const matches = (pattern: string, path: string) =>
    pattern === path || path.startsWith(`${pattern}.`) || (pattern.endsWith('*') && path.startsWith(pattern.slice(0, -1)));

  /** Fills computed fields (slugs and the like) from the rest of the state. */
  const withComputed = (page: Page): Page => ({
    ...page,
    state: walk([...page.commandBar, ...page.layout])
      .filter((node): node is FieldNode => 'computed' in node && node.computed !== undefined)
      .reduce((current, field) => setPath(current, field.path, evaluate(field.computed!, current)), page.state),
  });

  snapshot = { ...snapshot, page: withComputed(initial) };
  snapshot = { ...snapshot, baseline: snapshot.page.state };

  const pushToast = (message: string, tone = 'success') => {
    const id = ++toastId;
    set({ toasts: [...snapshot.toasts, { id, message, tone }] });
    setTimeout(() => set({ toasts: snapshot.toasts.filter((toast) => toast.id !== id) }), 5000);
  };

  /** A toast an action left before it redirected (sessionStorage, so a full page load keeps it too). */
  const showFlash = () => {
    const raw = sessionStorage.getItem(FLASH_KEY);
    if (!raw) return;
    sessionStorage.removeItem(FLASH_KEY);
    const toast = JSON.parse(raw) as { message: string; tone: string };
    pushToast(toast.message, toast.tone);
  };

  const insideModal = (nodeId: string, modalId: string) => {
    const modal = nodes().find((node) => node.type === 'modal' && node.id === modalId);
    return modal !== undefined && walk(modal.children ?? []).some((node) => node.id === nodeId);
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

    /** A new visit from Inertia (same screen re-rendered, e.g. a table sort). */
    receive(page: Page) {
      if (page === synced) return;
      synced = page;
      clearTimeout(timer);
      inflight?.abort();
      const next = withComputed(page);
      set({ page: next, errors: {}, pending: new Set(), running: null, modal: null, baseline: next.state });
      // The page an action redirected to: the store lives on across Inertia visits, so its toast shows here.
      showFlash();
    },

    setValue(path: string, value: unknown) {
      const field = nodes().find((node): node is FieldNode => node.path === path);
      const dependents = nodes().filter((node) => node.dependsOn?.some((pattern) => matches(pattern, path)));
      const errors = { ...snapshot.errors };
      if (!field?.liveValidation) {
        for (const key of Object.keys(errors)) if (key === path || key.startsWith(`${path}.`)) delete errors[key];
      }

      set({ page: withComputed({ ...snapshot.page, state: setPath(snapshot.page.state, path, value) }), errors });

      if (dependents.length === 0 && field?.live === undefined) return;

      const delay = field?.live ?? (field && DISCRETE.has(field.type) ? 0 : 300);
      set({ pending: new Set([...snapshot.pending, ...dependents.map((node) => node.id)]) });
      clearTimeout(timer);
      timer = setTimeout(runReaction, delay);
    },

    openModal(id: string | null) {
      set({ modal: id });
    },

    /**
     * Calls an #[Action] through the node with this id. Bulk actions pass the selected row ids,
     * input actions (tree moves, uploads) their named values.
     */
    async runAction(id: string, action: ActionRef, ids?: string[], input?: Record<string, unknown>): Promise<boolean> {
      clearTimeout(timer);
      inflight?.abort();
      set({ running: id, pending: new Set() });

      try {
        const { status, data } = await act(snapshot.page, id, action, ids, input);

        if (status === 422) {
          set({ errors: data.errors ?? {}, running: null, page: { ...snapshot.page, snapshot: data.snapshot ?? snapshot.page.snapshot } });
          pushToast(t('checkFields'), 'danger');
          const first = Object.keys(data.errors ?? {})[0];
          if (first) document.querySelector<HTMLElement>(`[data-vanda-path="${CSS.escape(first)}"] :is(input, button, textarea)`)?.focus();
          return false;
        }

        // A saved form is clean; an action inside a modal leaves the page's own changes alone.
        const fromModal = snapshot.modal !== null && insideModal(id, snapshot.modal);

        if (data.redirect) {
          if (data.toast) sessionStorage.setItem(FLASH_KEY, JSON.stringify(data.toast));
          set({ running: null, baseline: snapshot.page.state });
          router.visit(data.redirect);
          return true;
        }

        if (data.toast) pushToast(data.toast.message, data.toast.tone);

        const modal = data.openModal ?? (snapshot.modal && insideModal(id, snapshot.modal) ? null : snapshot.modal);

        if (data.page) {
          synced = data.page;
          router.replaceProp('page', data.page);
          const next = withComputed(data.page);
          set({ running: null, errors: {}, page: next, modal, baseline: fromModal ? snapshot.baseline : next.state });
        } else {
          set({ running: null, errors: {}, modal, page: { ...snapshot.page, snapshot: data.snapshot ?? snapshot.page.snapshot }, baseline: fromModal ? snapshot.baseline : snapshot.page.state });
        }

        return true;
      } catch (error) {
        set({ running: null });
        pushToast(t('requestFailed'), 'danger');
        console.error(error);
        return false;
      }
    },

    dismissToast(id: number) {
      set({ toasts: snapshot.toasts.filter((toast) => toast.id !== id) });
    },

    /** Shows a toast left by an action that redirected here (also after a full page load). */
    showFlash,
  };
}

export type Store = ReturnType<typeof createStore>;

const blank = (value: unknown) => (value === undefined || value === null || value === '' ? null : value);

/**
 * How many editable fields of the page (outside modals) differ from the last loaded or saved state.
 * A number, so components can subscribe to it without re-rendering on every keystroke.
 */
export function dirtyCount(snapshot: Snapshot): number {
  const fields: FieldNode[] = [];
  const visit = (nodes: UiNode[]) => {
    for (const node of nodes) {
      if (node.type === 'modal') continue;
      if (typeof node.path === 'string' && !node.disabled && !node.readonly && node.computed === undefined) fields.push(node as FieldNode);
      visit(node.children ?? []);
    }
  };
  visit(snapshot.page.layout);

  return fields.filter((field) => JSON.stringify(blank(getPath(snapshot.page.state, field.path))) !== JSON.stringify(blank(getPath(snapshot.baseline, field.path)))).length;
}

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

/** Same-origin URLs navigate through Inertia; anything else is a normal link. */
export function isInternal(href: string | undefined): href is string {
  if (!href) return false;
  try {
    return new URL(href, window.location.href).origin === window.location.origin;
  } catch {
    return false;
  }
}
