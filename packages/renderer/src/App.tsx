import { Link, router } from '@inertiajs/react';
import { useEffect, useLayoutEffect, useRef, useState, type ContextType, type ReactNode } from 'react';
import { Button as AriaButton, Dialog, DialogTrigger, Menu, MenuItem as AriaMenuItem, MenuTrigger, Modal, ModalOverlay, Popover, Tooltip, TooltipTriggerStateContext } from 'react-aria-components';
import { Inspector } from './Inspector';
import defaultAuthBackground from './assets/auth-background.webp';
import { VandaLogo, VandaSymbol } from './logo';
import { NotificationsBell } from './Notifications';
import { openPalette, Palette } from './Palette';
import { ConfirmDialog, DropDownView, type ButtonNode } from './nodes/actions';
import { isFormLayout } from './formLayout';
import { BlockList } from './nodes/layout';
import { NodeList, NodeView } from './nodes/NodeView';
import { COLLAPSED_GROUPS, DENSITY, FAVORITES, MENU_RAIL, useStoredList, useStoredValue } from './menuPrefs';
import { t, tn } from './i18n';
import { dirtyCount, dirtyFields, offerRestoreAfterReload, useSnapshot, useStore, type Store } from './store';
import { getPath } from './expr';
import { Avatar, buttonClass, cx, formatHotkey, Icon, Kbd, MOBILE_QUERY, useMediaQuery } from './ui';
import { onAccent } from './color';
import type { ActionRef, Crumb, Density, MenuItem, Page, UiNode } from './types';

export function App() {
  const page = useSnapshot((s) => s.page);
  const store = useStore();

  useEffect(() => store.showFlash(), [store]);

  // Leaving a form with unsaved changes asks first, in the panel's own dialog (links, the palette);
  // closing the tab or reloading gets the browser's question.
  const [leaving, setLeaving] = useState<{ url: string; count: number } | null>(null);
  useEffect(() => {
    const off = router.on('before', (event) => {
      const visit = event.detail.visit;
      if (visit.method !== 'get' || new URL(String(visit.url), window.location.href).pathname === window.location.pathname) return;
      if (leaveApproved) {
        leaveApproved = false;
        return;
      }
      const count = dirtyCount(store.get());
      if (count > 0) {
        event.preventDefault();
        setLeaving({ url: String(visit.url), count });
      }
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

  // A new page (not a sort, a filter or a page of the same list): its title takes the focus, so a
  // screen reader says where the user is and Tab goes on from the content, not from the old link.
  // (Inertia mounts the page anew on a visit, so the last address is kept outside it.)
  const path = new URL(page.url, window.location.href).pathname;
  useEffect(() => {
    const previous = lastPath;
    lastPath = path;
    if (previous === null || previous === path) return;
    // After the overlay that led here (the palette, the menu) has given the focus back.
    const timer = setTimeout(() => {
      if (!document.activeElement?.closest('[role="dialog"],[role="alertdialog"]')) focusContent();
    }, 50);
    return () => clearTimeout(timer);
  }, [path]);

  // The brand on <html>, not on this tree: dialogs, menus and calendars open in <body>, outside it.
  useLayoutEffect(() => {
    const root = document.documentElement.style;
    root.setProperty('--v-accent', page.app.brand);
    root.setProperty('--v-on-accent', onAccent(page.app.brand));
  }, [page.app.brand]);
  const mobile = useMediaQuery(MOBILE_QUERY);
  const [density] = useDensity();
  const [rail, toggleRail, roomy] = useRail();
  // Below 1024px the menu stays a rail and opens over the content when asked.
  const [drawer, setDrawer] = useState(false);
  useEffect(() => router.on('navigate', () => setDrawer(false)), []);
  useEffect(() => {
    if (roomy) setDrawer(false);
  }, [roomy]);
  // A page of forms keeps to 1120px (its header too); lists and dashboards take the full width.
  const measure = isFormLayout(page.layout) && 'max-w-280';

  useEffect(() => {
    document.documentElement.dataset.density = density;
  }, [density]);

  if (page.chrome === 'card') {
    // config('vanda.auth_background'): the picture fills the screen, a veil keeps the card calm on it.
    const background = page.app.authBackground === false ? null : (page.app.authBackground ?? defaultAuthBackground);
    return (
      <div
        className={cx('relative flex min-h-screen flex-col items-center justify-center gap-10 bg-bg bg-cover bg-center px-4 py-12')}
        style={background ? { backgroundImage: `url(${JSON.stringify(background)})` } : undefined}
      >
        {background && <div aria-hidden="true" className="absolute inset-0 bg-(--v-auth-veil)" />}
        <div className={cx('relative', background && 'rounded-2xl bg-surface/85 px-4 py-2.5 shadow-sm backdrop-blur-md')}>
          <Logo name={page.app.name} large />
        </div>
        <div className="relative flex w-full justify-center">
          <CardScreen page={page} glass={Boolean(background)} />
        </div>
        <Toasts />
      </div>
    );
  }

  // Phones get the app layout: a tab bar at the bottom and the whole menu in a sheet above it;
  // the page itself scrolls there (the browser's bars fold away). On a desktop the page stays
  // in place: the menu and the content card scroll each on their own, the card's header stays.
  return (
    // overflow-x-clip: nothing too wide may widen the page (on a phone that zooms everything out).
    <div
      className={cx(
        'flex flex-col bg-bg',
        // relative: absolutely placed things (sr-only tables of charts) must not lengthen the document.
        mobile ? 'min-h-screen overflow-x-clip bg-surface pb-[calc(64px+env(safe-area-inset-bottom))]' : 'relative h-dvh overflow-hidden print:h-auto print:overflow-visible',
      )}
    >
      {/* The first stop of Tab: past the menu, straight to the page (WCAG 2.4.1). */}
      <a
        href="#vanda-content"
        onClick={(event) => {
          event.preventDefault();
          focusContent();
        }}
        className="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-50 focus:rounded-lg focus:bg-surface focus:px-3.5 focus:py-2 focus:font-medium focus:text-fg focus:shadow-pop"
      >
        {t('skipToContent')}
      </a>
      <AlertStrips />
      <div className={cx('flex flex-1 items-stretch', !mobile && 'min-h-0')}>
        {!mobile && <Sidebar rail={rail} />}
        <main
          className={cx(
            'flex min-w-0 flex-1 flex-col bg-surface',
            mobile ? '[--v-gutter:16px]' : 'm-2 min-h-0 overflow-hidden rounded-[14px] border border-line shadow-sm print:overflow-visible',
          )}
        >
          {page.app.user?.impersonator && (
            <div role="status" className="flex flex-wrap items-center gap-x-3 gap-y-1 border-b border-line bg-warning-soft px-4 py-2 text-[13.5px] text-warning-fg md:rounded-t-[13px] md:px-8">
              <Icon name="eye" />
              <span>{t('impersonating', { name: page.app.user.name })}</span>
              <button type="button" onClick={() => router.post(page.app.user!.impersonator!.stop)} className="font-medium underline underline-offset-3 hover:no-underline">
                {t('stopImpersonating', { name: page.app.user.impersonator.name })}
              </button>
            </div>
          )}
          <header
            className={cx(
              'flex items-center justify-between gap-4 border-b border-line',
              // The toggle's icon lines up with the title below it.
              mobile ? 'sticky top-0 z-20 min-h-[52px] bg-surface/95 py-2 pr-2 pl-4 backdrop-blur' : 'min-h-[61px] py-3 pr-5 pl-6 compact:min-h-[53px] compact:py-2 compact:pl-4',
            )}
          >
            {mobile ? (
              <MobileTopBar crumbs={crumbsFor(page)} name={page.app.name} />
            ) : (
              <div className="flex min-w-0 items-center gap-2.5">
                <MenuToggle expanded={roomy ? !rail : drawer} controls={roomy ? 'vanda-menu' : 'vanda-menu-drawer'} onToggle={roomy ? toggleRail : () => setDrawer(true)} />
                <Breadcrumbs crumbs={crumbsFor(page)} title={page.title} />
              </div>
            )}
            <NotificationsBell />
          </header>

          {/* scroll-region: Inertia resets it to the top on a new page and brings it back on «Back». */}
          <div
            scroll-region={mobile ? undefined : ''}
            className={cx('flex flex-1 flex-col', !mobile && 'relative min-h-0 overflow-x-hidden overflow-y-auto print:overflow-visible')}
          >
            {/* A record's screen (with an avatar) gets the design's centred header with a rule under it. */}
            <section
              className={cx(
                mobile ? 'px-4' : 'px-8 compact:px-6',
                page.avatar ? cx('border-b border-line', mobile ? 'py-5' : 'py-7 compact:py-5') : cx('pb-5 compact:pb-4', mobile ? 'pt-5' : 'pt-7 compact:pt-5'),
              )}
            >
              <div className={cx('flex flex-wrap justify-between', page.avatar ? 'items-center gap-5' : 'items-end gap-4', measure)}>
                <div className="flex min-w-0 items-center gap-4">
                  {page.avatar && <Avatar initials={page.avatar.initials} hue={page.avatar.hue} image={page.avatar.image} size="lg" />}
                  <div className="flex min-w-0 flex-col gap-1">
                    <div className="flex flex-wrap items-center gap-2.5">
                      {page.title && (
                      // tabIndex -1: the target of «К содержимому» and of a new page; a heading needs no ring.
                      <h1 id="vanda-title" tabIndex={-1} className={cx('font-semibold tracking-[-0.015em] outline-none', mobile ? 'text-[20px] leading-7' : 'text-[24px] leading-8')}>
                        {page.title}
                      </h1>
                    )}
                      {page.badge && <NodeView node={page.badge} />}
                    </div>
                    {page.description && <p className="text-fg-2">{page.description}</p>}
                  </div>
                </div>
                {page.commandBar.length > 0 && (mobile ? <FoldedCommandBar nodes={page.commandBar} phone /> : <CommandBar nodes={page.commandBar} />)}
              </div>
            </section>

            <div className={cx(mobile ? 'px-4 pb-8' : 'px-8 pb-10 compact:px-6 compact:pb-8', page.avatar && (mobile ? 'pt-5' : 'pt-8 compact:pt-6'))}>
              <div id="vanda-content" tabIndex={-1} className={cx('flex flex-col gap-4 outline-none', measure)}>
                <BlockList nodes={page.layout} />
              </div>
            </div>
            <UnsavedBar mobile={mobile} />
          </div>
        </main>
      </div>
      {leaving && (
        <ConfirmDialog
          open
          onOpenChange={(open) => !open && setLeaving(null)}
          confirm={{ title: t('leaveTitle'), text: tn('leaveText', leaving.count), confirm: t('leave') }}
          danger
          icon="alert"
          label={t('leave')}
          cancelLabel={t('stay')}
          onConfirm={() => {
            leaveApproved = true;
            router.visit(leaving.url);
          }}
        />
      )}
      {!mobile && !roomy && (
        <ModalOverlay isOpen={drawer} onOpenChange={setDrawer} isDismissable className="fixed inset-0 z-40 bg-(--v-scrim) data-[entering]:animate-[vanda-fade-in_160ms_ease-out] motion-reduce:animate-none">
          <Modal className="fixed inset-y-0 left-0 flex border-r border-line bg-bg shadow-pop outline-none data-[entering]:animate-[vanda-drawer-in_220ms_cubic-bezier(0.2,0.8,0.2,1)] motion-reduce:animate-none">
            {/* A link in it leads away: the menu closes (also for the page already open). */}
            <Dialog aria-label={t('menu')} className="flex outline-none" onClick={(event) => (event.target as HTMLElement).closest('a') && setDrawer(false)}>
              <Sidebar rail={false} id="vanda-menu-drawer" />
            </Dialog>
          </Modal>
        </ModalOverlay>
      )}
      {mobile && <MobileNav />}
      <Toasts />
      <Palette />
      <Inspector />
    </div>
  );
}

/** The user chose «Уйти без сохранения»: the next visit goes without asking again. */
let leaveApproved = false;

/** The address of the page shown last: a new one moves the focus to its title. */
let lastPath: string | null = null;

/** Focus the page's title (else its content): where «К содержимому» and a new page lead. */
function focusContent() {
  (document.getElementById('vanda-title') ?? document.getElementById('vanda-content'))?.focus({ preventScroll: true });
}

/** Sign-in style screens: a centred card; Enter runs the first primary button. */
function CardScreen({ page, glass = false }: { page: Page; glass?: boolean }) {
  const store = useStore();
  const primary = page.layout.find((node): node is UiNode & { action: ActionRef } => node.type === 'button' && node.variant === 'primary' && Boolean(node.action));

  return (
    <form
      // On a picture the card turns to frosted glass, in tune with Vanda's liquid glass background.
      className={cx(
        'flex w-full max-w-[380px] flex-col gap-5 rounded-xl border p-8 shadow-card',
        glass ? 'border-(--v-glass-border) bg-surface/80 shadow-pop backdrop-blur-xl' : 'border-line bg-surface',
      )}
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

/**
 * The trail above the page. It leads up to the page: the page's own crumb would only repeat the
 * title right under it, so it is left out (a page with nothing above it keeps its one crumb).
 * More than three crumbs: the first, «…» with the ones between (a menu) and the last two. It stays
 * on one line: a long name ends with «…» (its own page shows it whole).
 */
function Breadcrumbs({ crumbs: all, title }: { crumbs: Crumb[]; title: string | null }) {
  const upward = all.length > 1 && title !== null && all[all.length - 1]!.label === title;
  const crumbs = upward ? all.slice(0, -1) : all;
  const hidden = crumbs.length > 3 ? crumbs.slice(1, -2) : [];
  const shown = hidden.length > 0 ? [crumbs[0]!, null, ...crumbs.slice(-2)] : crumbs;

  return (
    <nav aria-label={t('breadcrumbs')} className="min-w-0 text-[13.5px] text-fg-3">
      <ol className="flex min-w-0 items-center gap-1.5">
        {shown.map((crumb, index) => {
          // The page itself, when the trail ends with it.
          const last = index === shown.length - 1 && !upward;
          return (
            <li key={crumb ? `${crumb.label}-${index}` : '\u2026'} className={cx('flex min-w-0 items-center gap-1.5', !last && 'max-w-64 shrink-[2]')}>
              {index > 0 && <Icon name="chevron-right" className="size-3.5 shrink-0" />}
              {crumb === null ? (
                <HiddenCrumbs crumbs={hidden} />
              ) : last ? (
                <span aria-current="page" title={crumb.label} className="truncate font-medium text-fg">
                  {crumb.label}
                </span>
              ) : crumb.href ? (
                <Link href={crumb.href} title={crumb.label} className="truncate hover:text-fg">
                  {crumb.label}
                </Link>
              ) : (
                <span title={crumb.label} className="truncate">
                  {crumb.label}
                </span>
              )}
            </li>
          );
        })}
      </ol>
    </nav>
  );
}

/** «…» in a long trail: the crumbs it stands for, as a menu. */
function HiddenCrumbs({ crumbs }: { crumbs: Crumb[] }) {
  return (
    <MenuTrigger>
      <AriaButton aria-label={t('wholePath')} className="grid h-6 w-7 shrink-0 place-items-center rounded-md hover:bg-surface-2 hover:text-fg">
        <Icon name="more" className="size-4" />
      </AriaButton>
      <Popover placement="bottom start" className="max-w-80 min-w-48 rounded-xl border border-line bg-surface p-1 shadow-pop outline-none">
        <Menu
          aria-label={t('wholePath')}
          className="outline-none"
          disabledKeys={crumbs.flatMap((crumb, index) => (crumb.href ? [] : [String(index)]))}
          onAction={(key) => {
            const href = crumbs[Number(key)]?.href;
            if (href) router.visit(href);
          }}
        >
          {crumbs.map((crumb, index) => (
            <AriaMenuItem
              key={index}
              id={String(index)}
              textValue={crumb.label}
              className="flex h-8 cursor-pointer items-center rounded-md px-2 text-[14px] text-fg outline-none data-[disabled]:cursor-default data-[disabled]:text-fg-3 data-[focused]:bg-surface-2"
            >
              <span className="truncate">{crumb.label}</span>
            </AriaMenuItem>
          ))}
        </Menu>
      </Popover>
    </MenuTrigger>
  );
}

/**
 * Vanda's orchid next to the panel's name; a panel called "Vanda" shows the full logo.
 */
function Logo({ name, large = false, compact = false, rail = false }: { name: string; large?: boolean; compact?: boolean; rail?: boolean }) {
  if (rail) {
    return (
      <div className="flex justify-center pt-1 pb-3.5">
        <VandaSymbol className="h-7 w-auto" />
        <span className="sr-only">{name}</span>
      </div>
    );
  }

  if (name === 'Vanda') {
    return (
      <div className={cx('flex items-center', !large && !compact && 'px-1.5 pt-1.5 pb-4')}>
        <VandaLogo className={cx('w-auto text-fg', large ? 'h-9' : 'h-6')} />
      </div>
    );
  }

  return (
    <div className={cx('flex min-w-0 items-center gap-2', !large && !compact && 'px-1.5 pt-1 pb-3.5')}>
      <VandaSymbol className={cx('w-auto shrink-0', large ? 'h-9' : 'h-7')} />
      <span className={cx('truncate font-semibold tracking-[-0.01em]', large ? 'text-[18px]' : 'text-[15px]')}>{name}</span>
    </div>
  );
}

function menuGroups(menu: MenuItem[]): { title?: string; items: MenuItem[] }[] {
  return menu.reduce<{ title?: string; items: MenuItem[] }[]>((acc, item) => {
    const last = acc[acc.length - 1];
    if (last && last.title === item.group) last.items.push(item);
    else acc.push({ title: item.group, items: [item] });
    return acc;
  }, []);
}

/** Where a menu item is remembered as a favourite: its address, or its label when it has none. */
const menuKey = (item: MenuItem) => item.href ?? item.label;

/**
 * The panel's menu with its groups; `large` gives the taller rows of the phone sheet.
 * A group folds away under its title (remembered in this browser; the group of the current
 * page stays open), and a star puts an item into «Favorites» at the top.
 */
function MenuLinks({ menu, large = false }: { menu: MenuItem[]; large?: boolean }) {
  const [collapsed, toggleGroup] = useStoredList(COLLAPSED_GROUPS);
  const [favorites, toggleFavorite] = useStoredList(FAVORITES);
  const starred = favorites.map((key) => menu.find((item) => menuKey(item) === key)).filter((item): item is MenuItem => item !== undefined);
  const groups = [...(starred.length > 0 ? [{ title: t('favorites'), items: starred, favorites: true }] : []), ...menuGroups(menu)];

  return (
    <nav aria-label={t('menu')} className="flex flex-col">
      {groups.map((group, index) => {
        const foldable = Boolean(group.title) && !('favorites' in group);
        const open = !foldable || !collapsed.includes(group.title!) || group.items.some((item) => item.active);

        return (
          <div key={'favorites' in group ? '\u0000favorites' : (group.title ?? index)} className={cx('flex flex-col gap-0.5', 'favorites' in group && 'mb-1 border-b border-line pb-2.5')}>
            {group.title &&
              (foldable ? (
                <button
                  type="button"
                  aria-expanded={open}
                  title={t('toggleGroup', { label: group.title })}
                  onClick={() => toggleGroup(group.title!)}
                  className="group/title mt-[18px] mb-1.5 flex items-center gap-1 rounded-md px-2.5 compact:mt-3.5 text-left text-[11.5px] font-semibold tracking-[0.06em] text-fg-3 uppercase hover:text-fg-2"
                >
                  {group.title}
                  <Icon name="chevron-down" className={cx('size-3 opacity-0 transition-transform group-hover/title:opacity-100 group-focus-visible/title:opacity-100', !open && '-rotate-90 opacity-100')} />
                </button>
              ) : (
                <div className={cx('mb-1.5 px-2.5 text-[11.5px] font-semibold tracking-[0.06em] text-fg-3 uppercase', index > 0 && 'mt-[18px] compact:mt-3.5')}>{group.title}</div>
              ))}
            {open &&
              group.items.map((item) => {
                const isFavorite = favorites.includes(menuKey(item));
                return (
                  <div key={menuKey(item)} className="group/item relative">
                    <Link
                      href={item.href ?? '#'}
                      prefetch
                      aria-current={item.active ? 'page' : undefined}
                      className={cx(
                        'flex items-center gap-2.5 rounded-lg pl-2.5 font-medium text-fg-2 hover:bg-surface-2 hover:text-fg',
                        large ? 'h-11 pr-9 text-[15px]' : 'h-(--v-menu-item) pr-2.5 text-[14px]',
                        item.active && 'bg-surface text-fg shadow-sm ring-1 ring-line [&_svg]:text-accent-fg',
                      )}
                    >
                      <Icon name={item.icon} />
                      <span className={cx('truncate', !large && 'group-focus-within/item:pr-6 group-hover/item:pr-6')}>{item.label}</span>
                      {item.badge !== undefined && (
                        <span
                          className={cx(
                            'ml-auto grid h-5 min-w-5 shrink-0 place-items-center rounded-full bg-surface-3 px-1.5 text-[11.5px] font-semibold text-fg-2',
                            !large && 'group-focus-within/item:invisible group-hover/item:invisible',
                          )}
                        >
                          {item.badge}
                        </span>
                      )}
                    </Link>
                    {/* Phones have no hover: the star is always there, faint until it is on. Only the current
                        page's star is a stop of Tab (one per item would double the menu's stops); a screen
                        reader still finds every star, the keyboard stars the page that is open. */}
                    <button
                      type="button"
                      tabIndex={item.active ? undefined : -1}
                      aria-label={t(isFavorite ? 'removeFavorite' : 'addFavorite', { label: item.label })}
                      title={t(isFavorite ? 'removeFavorite' : 'addFavorite', { label: item.label })}
                      onClick={() => toggleFavorite(menuKey(item))}
                      className={cx(
                        'absolute top-1/2 right-1 grid size-7 -translate-y-1/2 place-items-center rounded-md hover:bg-surface-3',
                        isFavorite ? 'text-warning' : 'text-fg-3 hover:text-fg',
                        large
                          ? cx(!isFavorite && 'opacity-40', item.badge !== undefined && 'hidden')
                          : 'opacity-0 group-focus-within/item:opacity-100 group-hover/item:opacity-100 focus-visible:opacity-100',
                      )}
                    >
                      <Icon name="star" className={cx('size-3.5', isFavorite && 'fill-current')} />
                    </button>
                  </div>
                );
              })}
          </div>
        );
      })}
    </nav>
  );
}

/** Red strips over the whole page that the panel itself raises (a weak password on production). */
function AlertStrips() {
  const alerts = useSnapshot((s) => s.page.app.alerts);
  if (!alerts?.length) return null;

  return alerts.map((alert) => (
    <div
      key={alert.text}
      role="alert"
      className={cx(
        'flex flex-wrap items-center justify-center gap-x-3 gap-y-1 px-4 py-2 text-center text-[13px] font-medium text-white',
        alert.tone === 'danger' ? 'bg-danger' : 'bg-warning',
      )}
    >
      <span className="flex items-center gap-2">
        <Icon name="alert" className="size-4 shrink-0" />
        {alert.text}
      </span>
      {alert.link && (
        <Link href={alert.link.href} className="underline underline-offset-3 hover:no-underline">
          {alert.link.label}
        </Link>
      )}
    </div>
  ));
}

/** The signed-in user with the sign-out button, at the bottom of the menu. */
function UserCard() {
  const user = useSnapshot((s) => s.page.app.user);
  if (!user) return null;

  return (
    <div className="flex items-center gap-2.5 px-2 py-2">
      <Avatar initials={user.avatar.initials} hue={user.avatar.hue} image={user.avatar.image} />
      {/* With Panel::profile() the name leads to the user's own page. */}
      {user.profile ? (
        <Link href={user.profile} className="flex min-w-0 flex-grow flex-col rounded-md hover:text-accent-fg">
          <span className="truncate text-[13.5px] font-medium">{user.name}</span>
          <span className="truncate text-[12.5px] text-fg-3">{user.email}</span>
        </Link>
      ) : (
        <span className="flex min-w-0 flex-grow flex-col">
          <span className="truncate text-[13.5px] font-medium">{user.name}</span>
          <span className="truncate text-[12.5px] text-fg-3">{user.email}</span>
        </span>
      )}
      {user.logout && (
        <button
          type="button"
          aria-label={t('logout')}
          title={t('logout')}
          onClick={() => router.post(user.logout!)}
          className="grid size-9 shrink-0 place-items-center rounded-md text-fg-3 hover:bg-surface-2 hover:text-fg"
        >
          <Icon name="log-out" />
        </button>
      )}
    </div>
  );
}

/** Where the menu was scrolled: every visit mounts the sidebar anew, and the menu stays where it was. */
let menuScrollTop = 0;

/**
 * Whether the menu beside the content is a rail of icons, a way to switch it, and whether the
 * window has room for the open menu at all. From 1024px: the user's choice in this browser, else
 * a rail below 1280px. Narrower, it is always the rail (beside an open menu a form would be left
 * with half a screen); the toggle then opens the menu over the content.
 */
function useRail(): [rail: boolean, toggle: () => void, roomy: boolean] {
  const [stored, store] = useStoredValue(MENU_RAIL);
  const wide = useMediaQuery('(min-width: 1280px)');
  const roomy = useMediaQuery('(min-width: 1024px)');
  const rail = !roomy || (stored === null ? !wide : stored === '1');
  return [rail, () => store(rail ? '0' : '1'), roomy];
}

/** The density in use: the user's choice in this browser, else the panel's (config('vanda.density')). */
function useDensity(): [Density, (density: Density) => void] {
  const panel = useSnapshot((s) => s.page.app.density) ?? 'comfortable';
  const [stored, store] = useStoredValue(DENSITY);
  const density: Density = stored === 'compact' || stored === 'comfortable' ? stored : panel;
  // The panel's own density is not stored, so a later change of the config reaches this user.
  return [density, (next) => store(next === panel ? null : next)];
}

/** The button that folds the menu into a rail of icons and back (or opens it over the content), left of the breadcrumbs. */
function MenuToggle({ expanded, controls, onToggle }: { expanded: boolean; controls: string; onToggle: () => void }) {
  const label = t(expanded ? 'collapseMenu' : 'expandMenu');

  return (
    <>
      <button
        type="button"
        aria-controls={controls}
        aria-expanded={expanded}
        aria-label={label}
        title={label}
        onClick={onToggle}
        className="grid size-8 shrink-0 place-items-center rounded-lg text-fg-3 hover:bg-surface-2 hover:text-fg"
      >
        <Icon name={expanded ? 'sidebar-close' : 'sidebar-open'} />
      </button>
      <span aria-hidden="true" className="h-4 w-px shrink-0 bg-line" />
    </>
  );
}

function Sidebar({ rail, id = 'vanda-menu' }: { rail: boolean; id?: string }) {
  const app = useSnapshot((s) => s.page.app);
  const menu = useRef<HTMLDivElement>(null);

  useLayoutEffect(() => {
    const element = menu.current;
    if (!element) return;
    element.scrollTop = menuScrollTop;
    // A page opened by its address or from the palette brings its item into view (by hand:
    // scrollIntoView would also move the page's own scroll containers).
    const active = element.querySelector('[aria-current="page"]');
    if (!active) return;
    const box = element.getBoundingClientRect();
    const item = active.getBoundingClientRect();
    if (item.top < box.top || item.bottom > box.bottom) element.scrollTop += item.top - box.top - (box.height - item.height) / 2;
  }, [rail]);

  return (
    // As high as the window (the page does not scroll): the logo, search and the user stay in
    // place, only a long menu scrolls between them (as one scrolling column, flex squeezed the
    // search box to fit the screen).
    <aside id={id} className={cx('flex shrink-0 flex-col', rail ? 'w-16' : 'w-[248px]')}>
      <div className={cx('flex shrink-0 flex-col gap-1 px-3 pt-3.5', rail && 'items-center')}>
        <Logo name={app.name} rail={rail} />

        {rail ? (
          <RailTip label={t('search')} hint={formatHotkey('mod+k')}>
            <button
              type="button"
              aria-label={t('search')}
              aria-keyshortcuts="Meta+K"
              onClick={openPalette}
              className="grid h-(--v-control) w-10 place-items-center rounded-lg border border-line-strong bg-surface text-fg-3 hover:border-fg-3 hover:text-fg-2"
            >
              <Icon name="search" />
            </button>
          </RailTip>
        ) : (
          <button
            type="button"
            className="flex h-(--v-control) items-center gap-2 rounded-lg border border-line-strong bg-surface px-2.5 text-left text-fg-3 hover:border-fg-3 hover:text-fg-2"
            aria-label={t('search')}
            aria-keyshortcuts="Meta+K"
            onClick={openPalette}
          >
            <Icon name="search" />
            <span className="flex-grow text-[13.5px]">{t('search')}</span>
            <Kbd>{formatHotkey('mod+k')}</Kbd>
          </button>
        )}
      </div>

      <div
        ref={menu}
        onScroll={(event) => {
          menuScrollTop = event.currentTarget.scrollTop;
        }}
        className="min-h-0 flex-1 overflow-y-auto overscroll-contain px-3 pt-2 pb-4"
      >
        {rail ? <MenuRail menu={app.menu} /> : <MenuLinks menu={app.menu} />}
      </div>

      {rail ? (
        <div className="flex shrink-0 justify-center border-t border-line px-2 pt-3 pb-3.5">
          <RailAccount />
        </div>
      ) : (
        <div className="flex shrink-0 flex-col gap-2.5 border-t border-line px-3 pt-3 pb-3.5">
          <DisplaySwitches />
          <UserCard />
        </div>
      )}
    </aside>
  );
}

/** Until when the next rail label shows at once: running the mouse down the rail reads it item by item. */
let railTipsWarmUntil = 0;

/**
 * A rail button's label beside it: on hover after a moment, at once on keyboard focus. (React
 * Aria's TooltipTrigger needs a plain element, and Inertia's Link is a component; its Tooltip on
 * its own still reads the state from the context, so the state is given there.) The button keeps
 * its own aria-label; the label is for the eyes.
 */
function RailTip({ label, hint, children }: { label: string; hint?: string; children: ReactNode }) {
  const ref = useRef<HTMLDivElement>(null);
  const timer = useRef<ReturnType<typeof setTimeout>>(undefined);
  const [open, setOpen] = useState(false);

  const show = (delay: number) => {
    clearTimeout(timer.current);
    timer.current = setTimeout(() => setOpen(true), Date.now() < railTipsWarmUntil ? 0 : delay);
  };
  const hide = () => {
    clearTimeout(timer.current);
    if (open) railTipsWarmUntil = Date.now() + 400;
    setOpen(false);
  };
  useEffect(() => () => clearTimeout(timer.current), []);
  const state: TooltipState = { isOpen: open, shouldSkipAnimation: false, open: () => setOpen(true), close: hide };

  return (
    <div
      ref={ref}
      className="flex"
      onPointerEnter={(event) => event.pointerType === 'mouse' && show(300)}
      onPointerLeave={hide}
      onFocus={(event) => event.target.matches(':focus-visible') && show(0)}
      onBlur={hide}
      onPointerDown={hide}
      onKeyDown={(event) => event.key === 'Escape' && hide()}
    >
      {children}
      <TooltipTriggerStateContext.Provider value={state}>
        <Tooltip
          triggerRef={ref}
          placement="end"
          offset={12}
          className="pointer-events-none flex items-center gap-2 rounded-md bg-fg px-2 py-1 text-[12.5px] font-medium text-surface shadow-pop data-[entering]:animate-[vanda-fade-in_120ms_ease-out] motion-reduce:animate-none"
        >
          {label}
          {hint && <span className="text-surface/65 tabular-nums">{hint}</span>}
        </Tooltip>
      </TooltipTriggerStateContext.Provider>
    </div>
  );
}

type TooltipState = NonNullable<ContextType<typeof TooltipTriggerStateContext>>;

/**
 * The menu as a rail of icons: groups apart by a line, favourites on top, every item of a group
 * shown (a folded group has no title here to unfold it by). An item without an icon shows its
 * first letter; a badge becomes a dot, its number is in the label.
 */
function MenuRail({ menu }: { menu: MenuItem[] }) {
  const [favorites] = useStoredList(FAVORITES);
  const starred = favorites.map((key) => menu.find((item) => menuKey(item) === key)).filter((item): item is MenuItem => item !== undefined);
  const groups = [...(starred.length > 0 ? [{ key: '\u0000favorites', title: t('favorites'), items: starred }] : []), ...menuGroups(menu).map((group, index) => ({ key: group.title ?? String(index), ...group }))];

  return (
    <nav aria-label={t('menu')} className="flex flex-col items-center">
      {groups.map((group, index) => (
        <div
          key={group.key}
          role={group.title ? 'group' : undefined}
          aria-label={group.title}
          className={cx('flex w-full flex-col items-center gap-0.5', index > 0 && 'mt-2.5 border-t border-line pt-2.5')}
        >
          {group.items.map((item) => (
            <RailTip key={menuKey(item)} label={item.label} hint={item.badge === undefined ? undefined : String(item.badge)}>
              <Link
                href={item.href ?? '#'}
                prefetch
                aria-label={item.badge === undefined ? item.label : `${item.label} (${item.badge})`}
                aria-current={item.active ? 'page' : undefined}
                className={cx(
                  'relative grid h-(--v-menu-item) w-10 shrink-0 place-items-center rounded-lg text-fg-2 hover:bg-surface-2 hover:text-fg',
                  item.active && 'bg-surface text-fg shadow-sm ring-1 ring-line [&_svg]:text-accent-fg',
                )}
              >
                {item.icon ? <Icon name={item.icon} /> : <span aria-hidden="true" className="text-[13px] font-semibold">{Array.from(item.label.trim())[0]?.toUpperCase()}</span>}
                {item.badge !== undefined && <span aria-hidden="true" className="absolute top-1 right-1.5 size-2 rounded-full bg-accent ring-2 ring-bg" />}
              </Link>
            </RailTip>
          ))}
        </div>
      ))}
    </nav>
  );
}

/** The rail's foot: the user's avatar opens their card with the theme and density beside it. */
function RailAccount() {
  const user = useSnapshot((s) => s.page.app.user);

  return (
    <DialogTrigger>
      <AriaButton aria-label={t('account')} className="grid size-10 place-items-center rounded-full text-fg-3 hover:bg-surface-2 hover:text-fg">
        {user ? <Avatar initials={user.avatar.initials} hue={user.avatar.hue} image={user.avatar.image} /> : <Icon name="settings" />}
      </AriaButton>
      <Popover
        placement="end bottom"
        offset={12}
        className="w-[264px] rounded-xl border border-line bg-surface p-3 shadow-pop outline-none data-[entering]:animate-[vanda-fade-in_120ms_ease-out] motion-reduce:animate-none"
      >
        <Dialog aria-label={t('account')} className="flex flex-col gap-2.5 outline-none">
          <DisplaySwitches />
          <UserCard />
        </Dialog>
      </Popover>
    </DialogTrigger>
  );
}

/** Theme and density side by side; both are remembered in this browser. */
function DisplaySwitches() {
  return (
    <div className="flex flex-wrap items-center gap-2">
      <ThemeSwitch />
      <DensitySwitch />
    </div>
  );
}

/** Phones: the way back (the nearest crumb with a link) instead of the whole trail. */
function MobileTopBar({ crumbs, name }: { crumbs: Crumb[]; name: string }) {
  // A crumb that leads to this very page (the menu item of a list) is no way back.
  const here = window.location.pathname;
  const back = [...crumbs.slice(0, -1)].reverse().find((crumb) => crumb.href && new URL(crumb.href, window.location.href).pathname !== here);

  return back ? (
    <Link href={back.href!} className="-ml-1.5 flex h-9 min-w-0 items-center gap-1 rounded-lg pr-2 pl-1 text-[14px] font-medium text-fg-2 hover:bg-surface-2 hover:text-fg">
      <Icon name="chevron-left" className="size-5 shrink-0" />
      <span className="truncate">{back.label}</span>
    </Link>
  ) : (
    <Logo name={name} compact />
  );
}

const isPrimary = (node: UiNode) => ['button', 'link', 'modaltoggle'].includes(node.type) && node.variant === 'primary';

/**
 * The command bar with its secondary buttons in a «⋯» menu. Phones always show it so, the primary
 * buttons taking the width. On a desktop a button with a hotkey stays out too, with its key beside
 * it (inside the menu the key works as well, but nobody would learn it).
 */
function FoldedCommandBar({ nodes, phone = false }: { nodes: UiNode[]; phone?: boolean }) {
  const kept = nodes.filter((node) => isPrimary(node) || (!phone && Boolean(node.hotkey)));
  const rest = nodes.filter((node) => !kept.includes(node)).flatMap((node) => (node.type === 'dropdown' ? (node.children ?? []) : [node]));

  return (
    <div className={cx('flex gap-2', phone && 'w-full')}>
      {rest.length === 1 && kept.length === 0 ? (
        <NodeView node={rest[0]!} />
      ) : (
        rest.length > 0 && <DropDownView node={{ type: 'dropdown', id: 'commandbar.more', icon: 'more', variant: 'secondary', children: rest } as UiNode} />
      )}
      {kept.map((node) =>
        phone ? (
          <div key={node.id} className="flex flex-1 [&>*]:w-full">
            <NodeView node={node} />
          </div>
        ) : (
          <NodeView key={node.id} node={node} />
        ),
      )}
    </div>
  );
}

/**
 * The page's buttons beside its title. When they no longer fit there in one line, the secondary
 * ones fold into «⋯» (FoldedCommandBar); they come back when there is room again. The room is
 * measured at the natural widths of the title and of the whole bar, so nothing jumps back and forth.
 */
function CommandBar({ nodes }: { nodes: UiNode[] }) {
  const bar = useRef<HTMLDivElement>(null);
  const full = useRef(0);
  const [folded, setFolded] = useState(false);
  const kept = nodes.filter((node) => isPrimary(node) || Boolean(node.hotkey));
  const foldable = nodes.length - kept.length > 1 || (nodes.length > kept.length && kept.length > 0);

  // New buttons (another page, a changed record) are measured unfolded first.
  useLayoutEffect(() => setFolded(false), [nodes]);

  useLayoutEffect(() => {
    const element = bar.current;
    const row = element?.parentElement;
    const title = row?.firstElementChild;
    if (!element || !row || !(title instanceof HTMLElement) || !foldable) return;
    const natural = (box: HTMLElement) => {
      const { width, flexWrap } = box.style;
      box.style.width = 'max-content';
      box.style.flexWrap = 'nowrap';
      const size = box.getBoundingClientRect().width;
      box.style.width = width;
      box.style.flexWrap = flexWrap;
      return size;
    };
    const check = () => {
      if (!folded) full.current = natural(element);
      const gap = parseFloat(getComputedStyle(row).columnGap) || 0;
      setFolded(natural(title) + gap + full.current > row.clientWidth + 0.5);
    };
    check();
    const observer = new ResizeObserver(check);
    observer.observe(row);
    return () => observer.disconnect();
  }, [folded, foldable, nodes]);

  return (
    <div ref={bar} className="flex flex-wrap gap-2">
      {folded ? <FoldedCommandBar nodes={nodes} /> : <NodeList nodes={nodes} />}
    </div>
  );
}

const tabClass = (active: boolean) =>
  cx('flex min-w-0 flex-col items-center justify-center gap-1 text-[11px] font-medium', active ? 'text-accent-fg' : 'text-fg-3 hover:text-fg-2');

/**
 * Phones: a tab bar at the bottom, as in an app. The first three menu items, search and
 * "Menu", which opens the whole menu in a sheet from the bottom.
 */
function MobileNav() {
  const app = useSnapshot((s) => s.page.app);
  const url = useSnapshot((s) => s.page.url);
  const [open, setOpen] = useState(false);
  const [favorites] = useStoredList(FAVORITES);
  // The tab bar: items the app pinned (MenuItem::pinned()); else the user's favourites, topped up
  // with the first items of the menu.
  const appPinned = app.menu.filter((item) => item.pinned);
  const starred = favorites.map((key) => app.menu.find((item) => menuKey(item) === key)).filter((item): item is MenuItem => item !== undefined);
  const pinned = (appPinned.length > 0 ? appPinned : [...starred, ...app.menu.filter((item) => !starred.includes(item))]).slice(0, 3);
  const inSheet = !pinned.some((item) => item.active);

  // A link in the sheet opens another screen: the sheet goes away.
  useEffect(() => setOpen(false), [url]);

  return (
    <>
      <nav aria-label={t('menu')} className="fixed inset-x-0 bottom-0 z-30 border-t border-line bg-surface/95 pb-[env(safe-area-inset-bottom)] backdrop-blur">
        <div className="grid h-16" style={{ gridTemplateColumns: `repeat(${pinned.length + 2}, minmax(0, 1fr))` }}>
          {pinned.map((item) => (
            <Link key={menuKey(item)} href={item.href ?? '#'} prefetch aria-current={item.active ? 'page' : undefined} className={tabClass(Boolean(item.active))}>
              <span className="relative">
                <Icon name={item.icon} className="size-[22px]" />
                {item.badge !== undefined && (
                  <span className="absolute -top-2 left-3 grid h-[18px] min-w-[18px] place-items-center rounded-full bg-accent px-1 text-[11px] leading-none font-semibold text-on-accent ring-2 ring-surface tabular-nums">{item.badge}</span>
                )}
              </span>
              <span className="max-w-full truncate px-1">{item.label}</span>
            </Link>
          ))}
          <button type="button" onClick={openPalette} className={tabClass(false)}>
            <Icon name="search" className="size-[22px]" />
            <span>{t('find')}</span>
          </button>
          <button type="button" onClick={() => setOpen(true)} aria-expanded={open} className={tabClass(open || inSheet)}>
            <Icon name="menu" className="size-[22px]" />
            <span>{t('menuTab')}</span>
          </button>
        </div>
      </nav>

      <ModalOverlay
        isOpen={open}
        onOpenChange={setOpen}
        isDismissable
        className="fixed inset-0 z-40 bg-(--v-scrim) data-[entering]:animate-[vanda-fade-in_160ms_ease-out] motion-reduce:animate-none"
      >
        <Modal className="fixed inset-x-0 bottom-0 flex max-h-[88dvh] flex-col rounded-t-2xl bg-surface shadow-pop outline-none data-[entering]:animate-[vanda-sheet-in_220ms_cubic-bezier(0.2,0.8,0.2,1)] motion-reduce:animate-none">
          <Dialog aria-label={t('menu')} className="flex min-h-0 flex-col outline-none">
            <div aria-hidden="true" className="mx-auto mt-2 h-1 w-9 shrink-0 rounded-full bg-line-strong" />
            <div className="flex shrink-0 items-center justify-between px-4 pt-2 pb-1">
              <Logo name={app.name} compact />
              <button type="button" onClick={() => setOpen(false)} aria-label={t('close')} className="grid size-9 place-items-center rounded-lg text-fg-3 hover:bg-surface-2 hover:text-fg">
                <Icon name="x" />
              </button>
            </div>
            <div className="min-h-0 overflow-y-auto overscroll-contain px-3 pb-3">
              <MenuLinks menu={app.menu} large />
            </div>
            <div className="flex shrink-0 items-center justify-between gap-3 border-t border-line px-3 pt-2 pb-[calc(8px+env(safe-area-inset-bottom))]">
              <div className="min-w-0 flex-1">
                <UserCard />
              </div>
              <ThemeSwitch />
            </div>
          </Dialog>
        </Modal>
      </ModalOverlay>
    </>
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

  return (
    <SwitchGroup
      label={t('theme')}
      value={theme}
      onChange={setTheme}
      options={[
        { value: 'light', icon: 'sun', label: t('light') },
        { value: 'dark', icon: 'moon', label: t('dark') },
        { value: 'system', icon: 'monitor', label: t('system') },
      ]}
    />
  );
}

/** Comfortable or compact sizes on a desktop; phones always get the comfortable ones. */
function DensitySwitch() {
  const [density, choose] = useDensity();

  return (
    <SwitchGroup
      label={t('density')}
      value={density}
      onChange={choose}
      options={[
        { value: 'comfortable', icon: 'density-comfortable', label: t('comfortable') },
        { value: 'compact', icon: 'density-compact', label: t('compact') },
      ]}
    />
  );
}

/** A small row of icon buttons of which one is on (the theme, the density). */
function SwitchGroup<T extends string>({ label, value, onChange, options }: { label: string; value: T; onChange: (value: T) => void; options: { value: T; icon: string; label: string }[] }) {
  return (
    <div role="group" aria-label={label} className="inline-flex gap-0.5 self-start rounded-lg border border-line bg-surface-2 p-0.5">
      {options.map((option) => (
        <button
          key={option.value}
          type="button"
          aria-label={option.label}
          title={option.label}
          aria-pressed={value === option.value}
          onClick={() => onChange(option.value)}
          className={cx('grid h-6 w-7 place-items-center rounded-md text-fg-2 hover:text-fg', value === option.value && 'bg-surface text-fg shadow-sm ring-1 ring-line')}
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
  // Toasts rise above the unsaved changes bar instead of covering its buttons.
  const lifted = useUnsavedSave() !== null;

  return (
    <div
      aria-live="polite"
      className={cx(
        'pointer-events-none fixed z-50 flex flex-col items-end gap-2.5 transition-[bottom] duration-200 max-md:inset-x-3 md:right-5',
        lifted ? 'max-md:bottom-[calc(140px+env(safe-area-inset-bottom))] md:bottom-[88px]' : 'max-md:bottom-[calc(76px+env(safe-area-inset-bottom))] md:bottom-5',
      )}
    >
      {toasts.map((toast) => (
        <div
          key={toast.id}
          role={toast.tone === 'danger' ? 'alert' : 'status'}
          className="pointer-events-auto flex w-[360px] max-w-[calc(100vw-24px)] items-start max-md:w-full gap-2.5 rounded-xl border border-line bg-surface py-3 pr-3 pl-3.5 shadow-pop"
        >
          <span className={cx('mt-0.5', toast.tone === 'danger' ? 'text-danger' : toast.tone === 'neutral' ? 'text-fg-3' : 'text-success')}>
            <Icon name={toast.tone === 'danger' ? 'alert' : toast.tone === 'neutral' ? 'undo' : 'success'} />
          </span>
          <span className="flex-grow font-medium">{toast.message}</span>
          {toast.action && (
            <button
              type="button"
              onClick={() => {
                toast.action!.run();
                store.dismissToast(toast.id);
              }}
              className="-my-1 rounded-md px-2 py-1 font-semibold text-accent-fg hover:bg-accent-soft"
            >
              {toast.action.label}
            </button>
          )}
          <button type="button" aria-label={t('close')} onClick={() => store.dismissToast(toast.id)} className="text-fg-3 hover:text-fg">
            <Icon name="x" />
          </button>
        </div>
      ))}
    </div>
  );
}

/**
 * «Отменить» in the unsaved changes bar: the page loads afresh (so whatever the server drew for the
 * edited values goes too), and a toast offers «Вернуть», which types the edited values back in
 * (their reactions run again, as if the user had typed them).
 */
function discardChanges(store: Store) {
  const before = store.get();
  const fields = dirtyFields(before);
  // A Repeater and the fields of its rows both differ: the Repeater's own value brings back the rows.
  const edited = fields
    .filter((field) => !fields.some((other) => other !== field && field.path.startsWith(`${other.path}.`)))
    .map((field) => ({ path: field.path, value: getPath(before.page.state, field.path) }));

  // The reload mounts a new store: the offer waits for it (offerRestoreAfterReload).
  offerRestoreAfterReload(t('changesDiscarded'), t('restore'), edited);
  router.visit(window.location.href, { preserveScroll: true, replace: true });
}

/** "Unsaved changes · 3 fields · Discard · Save" at the bottom of a form, as in the design. */
/** The save button the unsaved changes bar offers, while there are changes to save. */
function useUnsavedSave(): ButtonNode | null {
  const count = useSnapshot(dirtyCount);
  const save = useSnapshot((s) => s.page.commandBar.find((node): node is ButtonNode => node.type === 'button' && node.variant === 'primary' && Boolean(node.action)));
  return save?.action && count > 0 ? save : null;
}

function UnsavedBar({ mobile }: { mobile: boolean }) {
  const store = useStore();
  const count = useSnapshot(dirtyCount);
  const running = useSnapshot((s) => s.running !== null);
  const save = useUnsavedSave();

  if (!save?.action) return null;

  return (
    <div
      role="status"
      className={cx(
        'sticky z-20 mt-2 flex items-center gap-3 rounded-xl border border-line bg-surface py-2 pr-2 pl-4 shadow-pop',
        mobile ? 'bottom-[calc(76px+env(safe-area-inset-bottom))] mx-3 mb-3' : 'bottom-4 mx-4 mb-6 self-center',
      )}
    >
      <span aria-hidden="true" className="size-2 shrink-0 rounded-full bg-warning" />
      <span className={cx('font-medium', mobile ? 'flex-1 text-[13.5px] leading-tight' : 'text-[14px]')}>{mobile ? tn('changedFields', count) : t('unsaved')}</span>
      {!mobile && <span className="text-[13.5px] text-fg-3">{tn('changedFields', count)}</span>}
      <button type="button" className={buttonClass('ghost', 'sm')} disabled={running} onClick={() => discardChanges(store)}>
        {t('discard')}
      </button>
      <button type="button" className={buttonClass('primary', 'sm')} disabled={running} onClick={() => void store.runAction(save.id, save.action!)}>
        {save.label}
        {save.hotkey && <Kbd inverted>{formatHotkey(save.hotkey)}</Kbd>}
      </button>
    </div>
  );
}
