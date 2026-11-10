import { router } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState, type ReactNode } from 'react';
import { Dialog, Modal, ModalOverlay } from 'react-aria-components';
import { t } from './i18n';
import { isInternal, useSnapshot, useStore } from './store';
import { useHotkey } from './hotkeys';
import { Avatar, cx, formatHotkey, Icon, Kbd, Spinner } from './ui';
import type { ActionRef } from './types';

/** Opens the palette from anywhere, e.g. the search button in the sidebar. */
export const openPalette = () => window.dispatchEvent(new Event('vanda:palette'));

interface Entry {
  key: string;
  group: string;
  label: string;
  subtitle?: string;
  icon?: string;
  initials?: string;
  hue?: number;
  image?: string;
  hotkey?: string;
  href?: string;
  run?: () => void;
}

interface Record {
  label: string;
  subtitle?: string;
  href: string;
  icon?: string;
  initials?: string;
  hue?: number;
  image?: string;
  group?: string;
}

function highlight(text: string, query: string): ReactNode {
  const index = query ? text.toLocaleLowerCase().indexOf(query.toLocaleLowerCase()) : -1;
  if (index < 0) return text;
  return (
    <>
      {text.slice(0, index)}
      <b className="font-semibold text-fg">{text.slice(index, index + query.length)}</b>
      {text.slice(index + query.length)}
    </>
  );
}

const matches = (text: string, query: string) => text.toLocaleLowerCase().includes(query.trim().toLocaleLowerCase());

/** ⌘K: pages from the menu, commands of the current screen and records from Vanda::search(). */
export function Palette() {
  const store = useStore();
  const page = useSnapshot((s) => s.page);
  const [open, setOpen] = useState(false);
  const [query, setQuery] = useState('');
  const [records, setRecords] = useState<Record[]>([]);
  // From the keystroke to the server's answer (the pause before the request included).
  const [searching, setSearching] = useState(false);
  const [active, setActive] = useState(0);
  const list = useRef<HTMLDivElement>(null);

  useHotkey('mod+k', () => setOpen((current) => !current));

  useEffect(() => {
    const onOpen = () => setOpen(true);
    window.addEventListener('vanda:palette', onOpen);
    return () => window.removeEventListener('vanda:palette', onOpen);
  }, []);

  useEffect(() => {
    if (!open) {
      setQuery('');
      setRecords([]);
    }
  }, [open]);

  // Records come from the server; the previous answer stays until the next one arrives.
  useEffect(() => {
    const search = page.app.search;
    if (!search || query.trim().length < 2) {
      setRecords([]);
      setSearching(false);
      return;
    }
    setSearching(true);
    const controller = new AbortController();
    const timer = setTimeout(() => {
      const url = new URL(search, window.location.href);
      url.searchParams.set('q', query.trim());
      fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin', signal: controller.signal })
        .then((response) => (response.ok ? response.json() : { results: [] }))
        .then((data: { results: Record[] }) => {
          setRecords(data.results);
          setSearching(false);
        })
        // A request cancelled by the next keystroke leaves the state to that one.
        .catch(() => {
          if (!controller.signal.aborted) setSearching(false);
        });
    }, 150);
    return () => {
      clearTimeout(timer);
      controller.abort();
    };
  }, [query, page.app.search]);

  const entries = useMemo<Entry[]>(() => {
    const goTo: Entry[] = page.app.menu
      .filter((item) => item.href && matches(item.label, query))
      .map((item) => ({ key: `menu:${item.href}`, group: t('goTo'), label: item.label, subtitle: item.group, icon: item.icon, href: item.href }));

    const commands: Entry[] = page.commandBar
      .filter((node) => (node.type === 'link' || (node.type === 'button' && node.action && !node.confirm)) && matches(String(node.label ?? ''), query))
      .map((node) => ({
        key: `command:${node.id}`,
        group: t('actions'),
        label: String(node.label),
        icon: typeof node.icon === 'string' ? node.icon : undefined,
        hotkey: typeof node.hotkey === 'string' ? node.hotkey : undefined,
        href: node.type === 'link' ? String(node.href) : undefined,
        run: node.type === 'button' ? () => void store.runAction(node.id, node.action as ActionRef) : undefined,
      }));

    const found: Entry[] = records.map((record, index) => ({ key: `record:${index}:${record.href}`, group: record.group ?? t('records'), ...record }));

    return [...goTo, ...commands, ...found];
  }, [page.app.menu, page.commandBar, records, query, store]);

  useEffect(() => setActive(0), [query]);
  useEffect(() => {
    list.current?.querySelector('[aria-selected="true"]')?.scrollIntoView({ block: 'nearest' });
  }, [active]);

  const choose = (entry: Entry | undefined, newTab = false) => {
    if (!entry) return;
    setOpen(false);
    if (entry.run) entry.run();
    else if (entry.href && newTab) window.open(entry.href, '_blank', 'noopener');
    else if (entry.href && isInternal(entry.href)) router.visit(entry.href);
    else if (entry.href) window.location.assign(entry.href);
  };

  let previousGroup: string | null = null;

  return (
    <ModalOverlay isOpen={open} onOpenChange={setOpen} isDismissable className="fixed inset-0 z-50 flex justify-center bg-(--v-scrim) px-4 pt-[12vh]">
      <Modal className="h-fit w-full max-w-[520px] overflow-hidden rounded-xl border border-line bg-surface shadow-pop outline-none">
        <Dialog aria-label={t('palette')} className="flex flex-col outline-none">
          <div className="flex items-center gap-3 border-b border-line px-4">
            {/* While the server looks for records, the magnifier turns into a spinner. */}
            <span className="grid size-5 shrink-0 place-items-center text-fg-3">{searching ? <Spinner className="size-4" /> : <Icon name="search" className="size-5" />}</span>
            <input
              autoFocus
              value={query}
              onChange={(event) => setQuery(event.target.value)}
              onKeyDown={(event) => {
                if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                  event.preventDefault();
                  const step = event.key === 'ArrowDown' ? 1 : -1;
                  setActive((current) => (entries.length === 0 ? 0 : (current + step + entries.length) % entries.length));
                } else if (event.key === 'Enter') {
                  event.preventDefault();
                  choose(entries[active], event.metaKey || event.ctrlKey);
                }
              }}
              role="combobox"
              aria-expanded="true"
              aria-controls="vanda-palette-results"
              aria-activedescendant={entries[active] ? `vanda-palette-${active}` : undefined}
              aria-label={t('palette')}
              placeholder={t('palette')}
              className="h-14 flex-1 bg-transparent text-[16px] text-fg outline-none placeholder:text-fg-3"
            />
            <Kbd>Esc</Kbd>
          </div>

          <div ref={list} id="vanda-palette-results" role="listbox" aria-label={t('palette')} className="max-h-[52vh] overflow-y-auto p-2">
            {entries.length === 0 && (
              <p aria-live="polite" className="px-3 py-8 text-center text-[14px] text-fg-3">
                {searching ? t('searching') : t('noResults')}
              </p>
            )}
            {entries.map((entry, index) => {
              const heading = entry.group !== previousGroup ? entry.group : null;
              previousGroup = entry.group;
              return (
                <div key={entry.key}>
                  {heading && <div className="px-3 pt-2.5 pb-1.5 text-[12px] font-medium text-fg-3">{heading}</div>}
                  <div
                    id={`vanda-palette-${index}`}
                    role="option"
                    aria-selected={index === active}
                    onMouseMove={() => setActive(index)}
                    onClick={(event) => choose(entry, event.metaKey || event.ctrlKey)}
                    className={cx('flex h-11 cursor-pointer items-center gap-3 rounded-lg px-3 text-[14px] text-fg-2', index === active && 'bg-surface-2 text-fg')}
                  >
                    {entry.initials ? (
                      <Avatar initials={entry.initials} hue={entry.hue} image={entry.image} size="sm" />
                    ) : (
                      <span className="grid size-6 place-items-center text-fg-3">
                        <Icon name={entry.icon ?? (entry.run ? 'chevron-right' : 'file')} />
                      </span>
                    )}
                    <span className="flex min-w-0 flex-1 flex-col">
                      <span className="truncate">{highlight(entry.label, query.trim())}</span>
                      {entry.subtitle && entry.initials && <span className="truncate text-[12.5px] text-fg-3">{highlight(entry.subtitle, query.trim())}</span>}
                    </span>
                    {entry.subtitle && !entry.initials && <span className="text-[12.5px] text-fg-3">{entry.subtitle}</span>}
                    {entry.hotkey && <Kbd>{formatHotkey(entry.hotkey)}</Kbd>}
                  </div>
                </div>
              );
            })}
          </div>

          <div className="flex flex-wrap items-center gap-4 border-t border-line px-4 py-2.5 text-[12.5px] text-fg-3">
            <span className="flex items-center gap-1">
              <Kbd>↑</Kbd>
              <Kbd>↓</Kbd> {t('navigate')}
            </span>
            <span className="flex items-center gap-1">
              <Kbd>↵</Kbd> {t('open')}
            </span>
            <span className="flex items-center gap-1">
              <Kbd>{formatHotkey('mod+↵')}</Kbd> {t('newTab')}
            </span>
          </div>
        </Dialog>
      </Modal>
    </ModalOverlay>
  );
}
