import { router } from '@inertiajs/react';
import { useCallback, useEffect, useState } from 'react';
import { Button as AriaButton, Dialog, DialogTrigger, Popover } from 'react-aria-components';
import { t } from './i18n';
import { isInternal, useSnapshot } from './store';
import { buttonClass, cx, Icon, Spinner } from './ui';

interface Item {
  id: string;
  title: string;
  message?: string;
  href?: string;
  tone?: string;
  when?: string;
  read?: boolean;
}

interface Payload {
  unread: number;
  items: Item[];
}

const TONE_ICONS: Record<string, string> = { success: 'success', warning: 'warning', danger: 'alert' };
const TONE_COLORS: Record<string, string> = {
  success: 'bg-success-soft text-success-fg',
  warning: 'bg-warning-soft text-warning-fg',
  danger: 'bg-danger-soft text-danger-fg',
};

function csrf(): Record<string, string> {
  const cookie = document.cookie.split('; ').find((part) => part.startsWith('XSRF-TOKEN='));
  return cookie ? { 'X-XSRF-TOKEN': decodeURIComponent(cookie.slice('XSRF-TOKEN='.length)) } : {};
}

/** The bell in the screen header: unread dot, the latest notifications, "mark all read". */
export function NotificationsBell() {
  const bell = useSnapshot((s) => s.page.app.notifications);
  const [data, setData] = useState<Payload | null>(null);
  const [tab, setTab] = useState<'all' | 'unread'>('all');
  const unread = data?.unread ?? bell?.unread ?? 0;

  const load = useCallback(async () => {
    if (!bell) return;
    const response = await fetch(bell.url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
    if (response.ok) setData((await response.json()) as Payload);
  }, [bell]);

  // A new page brings a fresh unread count; a minute of idling refreshes it too.
  useEffect(() => setData(null), [bell?.unread]);
  useEffect(() => {
    if (!bell) return;
    const timer = setInterval(() => void load(), 60_000);
    return () => clearInterval(timer);
  }, [bell, load]);

  if (!bell) return null;

  const markRead = async (id?: string) => {
    const response = await fetch(bell.read, {
      method: 'POST',
      headers: { Accept: 'application/json', 'Content-Type': 'application/json', ...csrf() },
      credentials: 'same-origin',
      body: JSON.stringify(id ? { id } : {}),
    });
    if (response.ok) setData((await response.json()) as Payload);
  };

  const open = (item: Item, close: () => void) => {
    if (!item.read) void markRead(item.id);
    if (!item.href) return;
    close();
    if (isInternal(item.href)) router.visit(item.href);
    else window.location.assign(item.href);
  };

  const items = (data?.items ?? []).filter((item) => tab === 'all' || !item.read);

  return (
    <DialogTrigger onOpenChange={(isOpen) => isOpen && void load()}>
      <AriaButton aria-label={unread > 0 ? t('notificationsUnread', { count: unread }) : t('notifications')} className={cx(buttonClass('ghost'), 'relative w-9 px-0')}>
        <Icon name="bell" />
        {unread > 0 && <span aria-hidden="true" className="absolute top-2 right-2.5 size-[7px] rounded-full bg-accent shadow-[0_0_0_2px_var(--v-surface)]" />}
      </AriaButton>
      <Popover placement="bottom end" className="w-[360px] max-w-[calc(100vw-32px)] overflow-hidden rounded-xl border border-line bg-surface shadow-pop outline-none">
        <Dialog aria-label={t('notifications')} className="flex flex-col outline-none">
          {({ close }) => (
            <>
              <div className="flex items-center justify-between px-4 pt-3.5">
                <h2 className="text-[15px] font-semibold">{t('notifications')}</h2>
                {unread > 0 && (
                  <button type="button" className={buttonClass('ghost', 'sm')} onClick={() => void markRead()}>
                    {t('readAll')}
                  </button>
                )}
              </div>
              <div role="tablist" className="flex gap-4 border-b border-line px-4">
                {(['all', 'unread'] as const).map((name) => (
                  <button
                    key={name}
                    type="button"
                    role="tab"
                    aria-selected={tab === name}
                    onClick={() => setTab(name)}
                    className={cx('py-2.5 text-[13.5px] font-medium text-fg-2 hover:text-fg', tab === name && 'text-fg shadow-[inset_0_-2px_0_var(--v-accent)]')}
                  >
                    {name === 'all' ? t('all') : `${t('unreadTab')}${unread > 0 ? ` · ${unread}` : ''}`}
                  </button>
                ))}
              </div>
              <ul className="max-h-[420px] overflow-y-auto">
                {data === null && (
                  <li className="grid place-items-center py-10 text-fg-3">
                    <Spinner />
                  </li>
                )}
                {data !== null && items.length === 0 && <li className="px-4 py-10 text-center text-[13.5px] text-fg-3">{t('noNotifications')}</li>}
                {items.map((item) => (
                  <li key={item.id} className="border-b border-line last:border-b-0">
                    <button
                      type="button"
                      onClick={() => open(item, close)}
                      className={cx('flex w-full gap-3 px-4 py-3 text-left hover:bg-surface-2', !item.read && 'bg-accent-soft/50')}
                    >
                      <span aria-hidden="true" className={cx('grid size-7 shrink-0 place-items-center rounded-full', TONE_COLORS[item.tone ?? ''] ?? 'bg-accent-soft text-accent-fg')}>
                        <Icon name={TONE_ICONS[item.tone ?? ''] ?? 'bell'} className="size-3.5" />
                      </span>
                      <span className="flex min-w-0 flex-grow flex-col gap-0.5">
                        <span className="text-[13.5px] font-medium">{item.title}</span>
                        {item.message && <span className="text-[13.5px] text-fg-2">{item.message}</span>}
                        {item.when && <span className="text-[12.5px] text-fg-3">{item.when}</span>}
                      </span>
                      {!item.read && <span aria-label={t('unreadOne')} className="mt-1.5 size-2 shrink-0 rounded-full bg-accent" />}
                    </button>
                  </li>
                ))}
              </ul>
            </>
          )}
        </Dialog>
      </Popover>
    </DialogTrigger>
  );
}
