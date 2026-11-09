import { Link } from '@inertiajs/react';
import { lang, t } from '../i18n';
import { relativeTime } from '../time';
import { nodeAttributes } from '../store';
import { Avatar, cx, Icon, MOBILE_QUERY, useMediaQuery } from '../ui';
import type { UiNode } from '../types';
import { BadgeView } from './layout';

interface Change {
  label: string;
  old?: string | null;
  new?: string | null;
  secret?: boolean;
}

interface Entry {
  id: number;
  event: string;
  eventLabel: string;
  tone: string;
  causer: { name: string; initials: string; hue?: number; image?: string; note?: string };
  at?: string;
  date?: string;
  action?: string;
  screen?: string;
  note?: string;
  subject?: string;
  subjectHref?: string;
  changes?: Change[];
  href?: string;
}

/** `entries` is left out of the IR when there are none. */
type ActivityNode = UiNode & { entries?: Entry[]; empty: string; more?: { label: string; href: string }; changesOnly?: boolean };

/**
 * The activity log of a record (Layout::activity()) or of one entry: who did what and when,
 * the values before and after. One border per level: entries are divided by lines.
 */
export function ActivityView({ node }: { node: ActivityNode }) {
  const entries = node.entries ?? [];

  if (node.changesOnly) {
    return (
      <div className="flex flex-col gap-4" {...nodeAttributes(node)}>
        {entries.map((entry) => (
          <Changes key={entry.id} changes={entry.changes ?? []} full />
        ))}
      </div>
    );
  }

  if (entries.length === 0) {
    return (
      <p className="text-[14px] text-fg-3" {...nodeAttributes(node)}>
        {node.empty}
      </p>
    );
  }

  return (
    <div className="flex flex-col" {...nodeAttributes(node)}>
      <ol className="flex flex-col">
        {entries.map((entry) => (
          <EntryView key={entry.id} entry={entry} />
        ))}
      </ol>
      {node.more && (
        <Link href={node.more.href} className="mt-1 inline-flex items-center gap-1 self-start border-t border-line pt-3 text-[13.5px] font-medium text-accent-fg hover:underline">
          {node.more.label}
          <Icon name="chevron-right" className="size-3.5" />
        </Link>
      )}
    </div>
  );
}

function EntryView({ entry }: { entry: Entry }) {
  const ago = relativeTime(entry.at, lang()) ?? entry.date;
  const time = (
    <time dateTime={entry.at} title={entry.date} className="text-[13px] whitespace-nowrap text-fg-3 tabular-nums">
      {ago}
    </time>
  );
  const where = [entry.action && `«${entry.action}»`, entry.screen, entry.note].filter(Boolean).join(' · ');

  return (
    <li className="flex gap-3 border-t border-line py-3.5 compact:py-2.5 first:border-t-0 first:pt-0 last:pb-0">
      <Avatar initials={entry.causer.initials} hue={entry.causer.hue} image={entry.causer.image} />
      <div className="flex min-w-0 flex-1 flex-col gap-1.5">
        <div className="flex flex-wrap items-center gap-x-2 gap-y-1">
          <span className="text-[14px] font-medium">{entry.causer.name}</span>
          <BadgeView node={{ id: `activity-${entry.id}`, type: 'badge', label: entry.eventLabel, tone: entry.tone, dot: true }} />
          <span className="ml-auto">
            {entry.href ? (
              <Link href={entry.href} className="rounded hover:text-fg hover:underline">
                {time}
              </Link>
            ) : (
              time
            )}
          </span>
        </div>
        {(entry.causer.note || entry.subject || where) && (
          <div className="flex flex-wrap gap-x-1.5 text-[13px] text-fg-3">
            {entry.subject &&
              (entry.subjectHref ? (
                <Link href={entry.subjectHref} className="font-medium text-fg-2 hover:underline">
                  {entry.subject}
                </Link>
              ) : (
                <span className="font-medium text-fg-2">{entry.subject}</span>
              ))}
            {entry.subject && where && <span aria-hidden="true">·</span>}
            {where && <span>{where}</span>}
            {entry.causer.note && <span className="text-warning-fg">· {entry.causer.note}</span>}
          </div>
        )}
        {entry.changes && entry.changes.length > 0 && <Changes changes={entry.changes} />}
      </div>
    </li>
  );
}

/** «Заголовок: Летняя акция → Осенняя акция», one line per field; a phone puts the value under its name. */
function Changes({ changes, full = false }: { changes: Change[]; full?: boolean }) {
  const mobile = useMediaQuery(MOBILE_QUERY);

  return (
    <dl className={cx('grid gap-x-4 gap-y-2 text-[13.5px]', mobile ? 'grid-cols-1' : full ? 'grid-cols-[minmax(120px,200px)_minmax(0,1fr)]' : 'grid-cols-[minmax(96px,160px)_minmax(0,1fr)]', !full && 'mt-0.5')}>
      {changes.map((change, index) => (
        <div key={`${change.label}-${index}`} className="contents">
          <dt className={cx('text-fg-3', mobile && index > 0 && 'mt-1.5')}>{change.label}</dt>
          <dd className="min-w-0">
            {change.secret ? (
              <span className="text-fg-3 italic">{t('activityHidden')}</span>
            ) : (
              <span className={cx('flex min-w-0 flex-wrap items-baseline gap-x-2 gap-y-1', !full && '[&>*]:line-clamp-3')}>
                {change.old !== undefined && change.old !== null && (
                  <del className="break-words whitespace-pre-line text-fg-3 decoration-fg-3/60">{change.old}</del>
                )}
                {change.old !== undefined && change.old !== null && change.new !== undefined && change.new !== null && (
                  <Icon name="arrow-right" className="size-3.5 shrink-0 self-center text-fg-3" />
                )}
                {change.new !== undefined && change.new !== null ? (
                  <ins className="break-words whitespace-pre-line text-fg no-underline">{change.new}</ins>
                ) : (
                  (change.old === undefined || change.old === null) && <span className="text-fg-3">{t('activityEmpty')}</span>
                )}
              </span>
            )}
          </dd>
        </div>
      ))}
    </dl>
  );
}
