import { Link, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { Inspector } from './Inspector';
import { NodeList } from './nodes/NodeView';
import { t } from './i18n';
import { useSnapshot, useStore } from './store';
import { cx, Icon, Kbd } from './ui';
import type { ActionRef, Crumb, MenuItem, Page, UiNode } from './types';

export function App() {
  const page = useSnapshot((s) => s.page);
  const store = useStore();

  useEffect(() => store.showFlash(), [store]);
  useEffect(() => {
    document.title = page.title ? `${page.title} — ${page.app.name}` : page.app.name;
  }, [page.title, page.app.name]);

  const brand = { '--v-accent': page.app.brand } as React.CSSProperties;

  if (page.chrome === 'card') {
    return (
      <div className="flex min-h-screen flex-col items-center justify-center gap-8 bg-bg px-4 py-12" style={brand}>
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
        <header className="flex items-center gap-4 border-b border-line px-8 py-3">
          <Breadcrumbs crumbs={crumbsFor(page)} />
        </header>

        <section className="flex flex-wrap items-end justify-between gap-4 px-8 pt-7 pb-6">
          <div className="flex min-w-0 flex-col gap-1">
            {page.title && <h1 className="text-[24px] leading-8 font-semibold tracking-[-0.015em]">{page.title}</h1>}
            {page.description && <p className="text-fg-2">{page.description}</p>}
          </div>
          {page.commandBar.length > 0 && (
            <div className="flex flex-wrap gap-2">
              <NodeList nodes={page.commandBar} />
            </div>
          )}
        </section>

        <div className="flex max-w-[1180px] flex-col gap-10 px-8 pb-10">
          <NodeList nodes={page.layout} />
        </div>
      </main>
      <Toasts />
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
      className="flex w-full max-w-[380px] flex-col gap-5 rounded-2xl border border-line bg-surface p-8 shadow-[0_1px_2px_rgb(24_24_27/0.04),0_4px_14px_rgb(24_24_27/0.06)]"
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
        className="flex h-9 items-center gap-2 rounded-lg border border-line-strong bg-surface px-2.5 text-left text-fg-3"
        aria-label={t('search')}
      >
        <Icon name="search" />
        <span className="flex-grow text-[13.5px]">{t('search')}</span>
        <Kbd>⌘K</Kbd>
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
            <span className="grid size-8 shrink-0 place-items-center rounded-full bg-accent-soft text-[12px] font-semibold text-accent-fg">
              {app.user.name.split(' ').map((part) => part[0]).join('').slice(0, 2).toUpperCase()}
            </span>
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
