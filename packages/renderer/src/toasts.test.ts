import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createStore, offerRestoreAfterReload } from './store';
import type { Page } from './types';

const page = (url: string): Page => ({
  schema: 'vanda/v1',
  screen: 'App\\Screens\\UserEditScreen',
  url,
  title: 'Анна Смирнова',
  description: null,
  commandBar: [],
  layout: [{ type: 'input', id: 'form.name', path: 'form.name' }],
  state: { form: { name: 'Анна' } },
  snapshot: 'signed',
  app: { name: 'Vanda', brand: '#12805c', menu: [], user: null },
});

describe('toasts with «Вернуть»', () => {
  beforeEach(() => {
    vi.useFakeTimers();
    vi.stubGlobal('window', { location: { pathname: '/admin/users/1/edit', href: 'http://panel.test/admin/users/1/edit' } });
    vi.stubGlobal('sessionStorage', { getItem: () => null, removeItem: () => {}, setItem: () => {} });
  });
  afterEach(() => {
    vi.useRealTimers();
    vi.unstubAllGlobals();
  });

  it('stay until used or closed, while a plain toast leaves after five seconds', () => {
    const store = createStore(page('/admin/users/1/edit'));
    store.notify('Сохранено');
    store.notify('Строка 2 удалена', { tone: 'neutral', action: { label: 'Вернуть', run: () => {} } });

    vi.advanceTimersByTime(60_000);

    expect(store.get().toasts.map((toast) => toast.message)).toEqual(['Строка 2 удалена']);
  });

  it('replace the previous one of the same key: the last row removed, not a pile', () => {
    const store = createStore(page('/admin/users/1/edit'));
    const action = { label: 'Вернуть', run: () => {} };
    store.notify('Строка 1 удалена', { tone: 'neutral', key: 'removed:form.rows', action });
    store.notify('Строка 3 удалена', { tone: 'neutral', key: 'removed:form.rows', action });
    store.notify('Строка 1 удалена', { tone: 'neutral', key: 'removed:form.tags', action });

    expect(store.get().toasts.map((toast) => toast.message)).toEqual(['Строка 3 удалена', 'Строка 1 удалена']);
  });

  it('leave with their page, but outlive a reload of the same page (the discarded changes)', () => {
    const store = createStore(page('/admin/users/1/edit'));
    store.notify('Изменения отменены', { tone: 'neutral', action: { label: 'Вернуть', run: () => {} } });

    store.receive(page('http://panel.test/admin/users/1/edit?tab=1'));
    expect(store.get().toasts).toHaveLength(1);

    store.receive(page('http://panel.test/admin/users'));
    expect(store.get().toasts).toHaveLength(0);
  });

  it('carry «Вернуть» over the reload that discards the changes, into the new store', () => {
    offerRestoreAfterReload('Изменения отменены', 'Вернуть', [{ path: 'form.name', value: 'Анна Петровна' }]);
    const reloaded = createStore(page('/admin/users/1/edit'));
    const [toast] = reloaded.get().toasts;

    expect(toast?.message).toBe('Изменения отменены');
    toast!.action!.run();
    expect(reloaded.get().page.state).toEqual({ form: { name: 'Анна Петровна' } });
    // Taken once: the next store of this page starts clean.
    expect(createStore(page('/admin/users/1/edit')).get().toasts).toHaveLength(0);
  });
});
