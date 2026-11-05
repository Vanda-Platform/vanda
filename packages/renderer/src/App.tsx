import { Link, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { Inspector } from './Inspector';
import { NotificationsBell } from './Notifications';
import { openPalette, Palette } from './Palette';
import type { ButtonNode } from './nodes/actions';
import { NodeList, NodeView } from './nodes/NodeView';
import { t, tn } from './i18n';
import { dirtyCount, useSnapshot, useStore } from './store';
import { Avatar, buttonClass, cx, formatHotkey, Icon, Kbd } from './ui';
import type { ActionRef, Crumb, MenuItem, Page, UiNode } from './types';

export function App() {
  const page = useSnapshot((s) => s.page);
  const store = useStore();

  useEffect(() => store.showFlash(), [store]);

  // Leaving a form with unsaved changes asks first: links, the palette, the browser's back and close.
  useEffect(() => {
    const off = router.on('before', (event) => {
      const visit = event.detail.visit;
      if (visit.method !== 'get' || new URL(String(visit.url), window.location.href).pathname === window.location.pathname) return;
      if (dirtyCount(store.get()) > 0 && !window.confirm(t('leaveUnsaved'))) event.preventDefault();
    });
    const onUnload = (event: BeforeUnloadEvent) => {
      if (dirtyCount(store.get()) > 0) event.preventDefault();
    };
    window.addEventListener('beforeunload', onUnload);
    return () => {
      off();
      window.removeEventListener('beforeunload', onUnload);
    };
  }, [store]);
  useEffect(() => {
    document.title = page.title ? `${page.title} — ${page.app.name}` : page.app.name;
  }, [page.title, page.app.name]);

  const brand = { '--v-accent': page.app.brand } as React.CSSProperties;

  if (page.chrome === 'card') {
    return (
      <div className="flex min-h-screen flex-col items-center justify-center gap-10 bg-bg px-4 py-12" style={brand}>
        <Logo name={page.app.name} large />
        <CardScreen page={page} />
        <Toasts />
      </div>
    );
  }

  return (
    <div className="flex min-h-screen flex-wrap items-stretch bg-bg" style={brand}>
      <Sidebar />
      <main className="m-2 flex min-w-0 flex-[999_1_560px] flex-col rounded-[14px] border border-line bg-surface shadow-sm">
        {page.app.user?.impersonator && (
          <div role="status" className="flex flex-wrap items-center gap-x-3 gap-y-1 rounded-t-[13px] border-b border-line bg-warning-soft px-8 py-2 text-[13.5px] text-warning-fg">
            <Icon name="eye" />
            <span>{t('impersonating', { name: page.app.user.name })}</span>
            <button type="button" onClick={() => router.post(page.app.user!.impersonator!.stop)} className="font-medium underline underline-offset-3 hover:no-underline">
              {t('stopImpersonating', { name: page.app.user.impersonator.name })}
            </button>
          </div>
        )}
        <header className="flex min-h-[61px] items-center justify-between gap-4 border-b border-line py-3 pr-5 pl-8">
          <Breadcrumbs crumbs={crumbsFor(page)} />
          <NotificationsBell />
        </header>

        {/* A record's screen (with an avatar) gets the design's centred header with a rule under it. */}
        <section className={cx('flex flex-wrap justify-between gap-4 px-8', page.avatar ? 'items-center gap-5 border-b border-line py-7' : 'items-end pt-7 pb-5')}>
          <div className="flex min-w-0 items-center gap-4">
            {page.avatar && <Avatar initials={page.avatar.initials} hue={page.avatar.hue} image={page.avatar.image} size="lg" />}
            <div className="flex min-w-0 flex-col gap-1">
              <div className="flex flex-wrap items-center gap-2.5">
                {page.title && <h1 className="text-[24px] leading-8 font-semibold tracking-[-0.015em]">{page.title}</h1>}
                {page.badge && <NodeView node={page.badge} />}
              </div>
              {page.description && <p className="text-fg-2">{page.description}</p>}
            </div>
          </div>
          {page.commandBar.length > 0 && (
            <div className="flex flex-wrap gap-2">
              <NodeList nodes={page.commandBar} />
            </div>
          )}
        </section>

        <div className={cx('flex flex-col gap-4 px-8 pb-10', page.avatar && 'pt-8')}>
          <NodeList nodes={page.layout} />
        </div>
        <UnsavedBar />
      </main>
      <Toasts />
      <Palette />
      <Inspector />
    </div>
  );
}

/** Sign-in style screens: a centred card; Enter runs the first primary button. */
function CardScreen({ page }: { page: Page }) {
  const store = useStore();
  const primary = page.layout.find((node): node is UiNode & { action: ActionRef } => node.type === 'button' && node.variant === 'primary' && Boolean(node.action));

  return (
    <form
      className="flex w-full max-w-[380px] flex-col gap-5 rounded-xl border border-line bg-surface p-8 shadow-card"
      onSubmit={(event) => {
        event.preventDefault();
        if (primary) void store.runAction(primary.id, primary.action);
      }}
    >
      <div className="flex flex-col gap-1.5">
        {page.title && <h1 className="text-[18px] leading-[26px] font-semibold tracking-[-0.01em]">{page.title}</h1>}
        {page.description && <p className="text-[13.5px] text-fg-2">{page.description}</p>}
      </div>
      <NodeList nodes={page.layout} />
      {/* A form with several inputs submits on Enter only if it has a submit button. */}
      <button type="submit" tabIndex={-1} aria-hidden="true" className="sr-only" />
    </form>
  );
}

function crumbsFor(page: Page): Crumb[] {
  if (page.breadcrumbs && page.breadcrumbs.length > 0) return page.breadcrumbs;

  const active = page.app.menu.find((item) => item.active);
  const crumbs: Crumb[] = [];
  if (active?.group) crumbs.push({ label: active.group });
  if (active && active.label !== page.title) crumbs.push({ label: active.label, href: active.href });
  if (page.title) crumbs.push({ label: page.title });
  return crumbs;
}

function Breadcrumbs({ crumbs }: { crumbs: Crumb[] }) {
  return (
    <nav aria-label={t('breadcrumbs')} className="flex flex-wrap items-center gap-1.5 text-[13.5px] text-fg-3">
      {crumbs.map((crumb, index) => {
        const last = index === crumbs.length - 1;
        return (
          <span key={`${crumb.label}-${index}`} className="flex items-center gap-1.5">
            {index > 0 && <Icon name="chevron-right" className="size-3.5" />}
            {last ? (
              <span aria-current="page" className="font-medium text-fg">
                {crumb.label}
              </span>
            ) : crumb.href ? (
              <Link href={crumb.href} className="hover:text-fg">
                {crumb.label}
              </Link>
            ) : (
              <span>{crumb.label}</span>
            )}
          </span>
        );
      })}
    </nav>
  );
}

function Logo({ name, large = false }: { name: string; large?: boolean }) {
  return (
    <div className={cx('flex items-center gap-2.5', !large && 'px-1.5 pt-1 pb-3.5')}>
      <span aria-hidden="true" className={cx('grid place-items-center rounded-lg bg-accent text-white', large ? 'size-8' : 'size-7')}>
        <svg viewBox="0 0 24 24" className={large ? 'size-5' : 'size-[18px]'} fill="none" stroke="currentColor" strokeWidth={1.9} strokeLinecap="round" strokeLinejoin="round">
          <path d="M12 20C7 17 4.5 11 5 4.5c5 1.5 7.5 6.5 7 15.5ZM12 20c5-3 7.5-9 7-15.5-5 1.5-7.5 6.5-7 15.5Z" />
        </svg>
      </span>
      <span className={cx('font-semibold tracking-[-0.01em]', large ? 'text-[18px]' : 'text-[15px]')}>{name}</span>
    </div>
  );
}

function Sidebar() {
  const app = useSnapshot((s) => s.page.app);
  const groups = app.menu.reduce<{ title?: string; items: MenuItem[] }[]>((acc, item) => {
    const last = acc[acc.length - 1];
    if (last && last.title === item.group) last.items.push(item);
    else acc.push({ title: item.group, items: [item] });
    return acc;
  }, []);

  return (
    <aside className="flex flex-[1_1_248px] flex-col gap-1 px-3 py-3.5 md:max-w-[248px]">
      <Logo name={app.name} />

      <button
        type="button"
        className="flex h-9 items-center gap-2 rounded-lg border border-line-strong bg-surface px-2.5 text-left text-fg-3 hover:border-fg-3 hover:text-fg-2"
        aria-label={t('search')}
        aria-keyshortcuts="Meta+K"
        onClick={openPalette}
      >
        <Icon name="search" />
        <span className="flex-grow text-[13.5px]">{t('search')}</span>
        <Kbd>{formatHotkey('mod+k')}</Kbd>
      </button>

      <nav aria-label={t('menu')} className="mt-2 flex flex-col">
        {groups.map((group, index) => (
          <div key={group.title ?? index} className="flex flex-col gap-0.5">
            {group.title && <div className="mt-[18px] mb-1.5 px-2.5 text-[11.5px] font-semibold tracking-[0.06em] text-fg-3 uppercase">{group.title}</div>}
            {group.items.map((item) => (
              <Link
                key={item.label}
                href={item.href ?? '#'}
                prefetch
                aria-current={item.active ? 'page' : undefined}
                className={cx(
                  'flex h-[34px] items-center gap-2.5 rounded-lg px-2.5 text-[14px] font-medium text-fg-2 hover:bg-surface-2 hover:text-fg',
                  item.active && 'bg-surface text-fg shadow-sm ring-1 ring-line [&_svg]:text-accent-fg',
                )}
              >
                <Icon name={item.icon} />
                <span>{item.label}</span>
                {item.badge !== undefined && (
                  <span className="ml-auto grid h-5 min-w-5 place-items-center rounded-full bg-surface-3 px-1.5 text-[11.5px] font-semibold text-fg-2">{item.badge}</span>
                )}
              </Link>
            ))}
          </div>
        ))}
      </nav>

      <div className="mt-auto flex flex-col gap-2.5 pt-4">
        <ThemeSwitch />
        {app.user && (
          <div className="flex items-center gap-2.5 px-2 py-2">
            <Avatar initials={app.user.avatar.initials} hue={app.user.avatar.hue} image={app.user.avatar.image} />
            <span className="flex min-w-0 flex-grow flex-col">
              <span className="truncate text-[13.5px] font-medium">{app.user.name}</span>
              <span className="truncate text-[12.5px] text-fg-3">{app.user.email}</span>
            </span>
            {app.user.logout && (
              <button
                type="button"
                aria-label={t('logout')}
                title={t('logout')}
                onClick={() => router.post(app.user!.logout!)}
                className="grid size-7 shrink-0 place-items-center rounded-md text-fg-3 hover:bg-surface-2 hover:text-fg"
              >
                <Icon name="log-out" />
              </button>
            )}
          </div>
        )}
      </div>
    </aside>
  );
}

type Theme = 'light' | 'dark' | 'system';

function applyTheme(theme: Theme) {
  const dark = theme === 'dark' || (theme === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
  document.documentElement.dataset.theme = dark ? 'dark' : 'light';
}

function ThemeSwitch() {
  const [theme, setTheme] = useState<Theme>(() => {
    try {
      return (localStorage.getItem('vanda:theme') as Theme | null) ?? 'system';
    } catch {
      return 'system';
    }
  });

  useEffect(() => {
    applyTheme(theme);
    try {
      localStorage.setItem('vanda:theme', theme);
    } catch {
      // Private mode: the choice simply is not remembered.
    }
  }, [theme]);

  const options: { value: Theme; icon: string; label: string }[] = [
    { value: 'light', icon: 'sun', label: t('light') },
    { value: 'dark', icon: 'moon', label: t('dark') },
    { value: 'system', icon: 'monitor', label: t('system') },
  ];

  return (
    <div role="group" aria-label={t('theme')} className="inline-flex gap-0.5 self-start rounded-lg border border-line bg-surface-2 p-0.5">
      {options.map((option) => (
        <button
          key={option.value}
          type="button"
          aria-label={option.label}
          aria-pressed={theme === option.value}
          onClick={() => setTheme(option.value)}
          className={cx(
            'grid h-6 w-7 place-items-center rounded-md text-fg-2 hover:text-fg',
            theme === option.value && 'bg-surface text-fg shadow-sm ring-1 ring-line',
          )}
        >
          <Icon name={option.icon} className="size-3.5" />
        </button>
      ))}
    </div>
  );
}

function Toasts() {
  const toasts = useSnapshot((s) => s.toasts);
  const store = useStore();

  return (
    <div aria-live="polite" className="pointer-events-none fixed right-5 bottom-5 z-50 flex flex-col items-end gap-2.5">
      {toasts.map((toast) => (
        <div
          key={toast.id}
          role={toast.tone === 'danger' ? 'alert' : 'status'}
          className="pointer-events-auto flex w-[360px] max-w-[calc(100vw-40px)] items-start gap-2.5 rounded-xl border border-line bg-surface py-3 pr-3 pl-3.5 shadow-pop"
        >
          <span className={cx('mt-0.5', toast.tone === 'danger' ? 'text-danger' : 'text-success')}>
            <Icon name={toast.tone === 'danger' ? 'alert' : 'success'} />
          </span>
          <span className="flex-grow font-medium">{toast.message}</span>
          <button type="button" aria-label={t('close')} onClick={() => store.dismissToast(toast.id)} className="text-fg-3 hover:text-fg">
            <Icon name="x" />
          </button>
        </div>
      ))}
    </div>
  );
}

/** "Unsaved changes · 3 fields · Discard · Save" at the bottom of a form, as in the design. */
function UnsavedBar() {
  const store = useStore();
  const count = useSnapshot(dirtyCount);
  const running = useSnapshot((s) => s.running !== null);
  const save = useSnapshot((s) => s.page.commandBar.find((node): node is ButtonNode => node.type === 'button' && node.variant === 'primary' && Boolean(node.action)));

  if (!save?.action || count === 0) return null;

  return (
    <div role="status" className="sticky bottom-4 z-20 mx-4 mt-2 mb-6 flex items-center gap-3 self-center rounded-xl border border-line bg-surface py-2 pr-2 pl-4 shadow-pop">
      <span aria-hidden="true" className="size-2 rounded-full bg-warning" />
      <span className="text-[14px] font-medium">{t('unsaved')}</span>
      <span className="text-[13.5px] text-fg-3">{tn('changedFields', count)}</span>
      <button
        type="button"
        className={buttonClass('ghost', 'sm')}
        disabled={running}
        onClick={() => router.visit(window.location.href, { preserveScroll: true, replace: true })}
      >
        {t('discard')}
      </button>
      <button type="button" className={buttonClass('primary', 'sm')} disabled={running} onClick={() => void store.runAction(save.id, save.action!)}>
        {save.label}
        {save.hotkey && <Kbd inverted>{formatHotkey(save.hotkey)}</Kbd>}
      </button>
    </div>
  );
}
