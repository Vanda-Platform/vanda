/** «5 минут назад», «вчера»; a week and older: null, the date itself is clearer. */
export function relativeTime(iso: string | undefined, language: string, now = Date.now()): string | null {
  if (!iso) return null;
  const seconds = Math.round((new Date(iso).getTime() - now) / 1000);
  const span = Math.abs(seconds);
  const format = new Intl.RelativeTimeFormat(language, { numeric: 'auto' });

  if (span < 45) return format.format(0, 'second');
  if (span < 3600) return format.format(Math.round(seconds / 60), 'minute');
  if (span < 86400) return format.format(Math.round(seconds / 3600), 'hour');
  if (span < 7 * 86400) return format.format(Math.round(seconds / 86400), 'day');
  return null;
}
