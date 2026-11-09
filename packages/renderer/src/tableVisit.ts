import { router } from '@inertiajs/react';

/** A filter's value in the URL: a word, or a period of two dates. */
export type FilterValue = string | { start: string | null; end: string | null };

/**
 * The header a table's own controls send with their visits (Vanda\Screen\RememberedView::HEADER):
 * the server saves the view they lead to as the user's, an empty one after «Reset» too. Visits
 * without it and without the table's parameters (the menu, a breadcrumb) get the saved view back.
 */
export const TABLE_VISIT_HEADERS = { 'X-Vanda-Table': '1' } as const;

/** The address of this screen with changed query parameters (and filters, which start on page 1). */
export function urlWith(params: Record<string, string | null>, filters?: Record<string, FilterValue | null>): string {
  const url = new URL(window.location.href);
  for (const [key, value] of Object.entries(params)) {
    if (value === null || value === '') url.searchParams.delete(key);
    else url.searchParams.set(key, value);
  }
  if (filters) {
    for (const key of [...url.searchParams.keys()]) if (key.startsWith('filter[')) url.searchParams.delete(key);
    for (const [name, value] of Object.entries(filters)) {
      if (value === null || value === '') continue;
      if (typeof value === 'string') url.searchParams.set(`filter[${name}]`, value);
      else {
        if (value.start) url.searchParams.set(`filter[${name}][start]`, value.start);
        if (value.end) url.searchParams.set(`filter[${name}][end]`, value.end);
      }
    }
    url.searchParams.delete('page');
  }
  return url.toString();
}

/** Visit the same screen with changed query parameters, keeping scroll and local state; the server remembers the view. */
export function visitWith(params: Record<string, string | null>, filters?: Record<string, FilterValue | null>) {
  router.visit(urlWith(params, filters), { preserveScroll: true, preserveState: true, replace: true, headers: TABLE_VISIT_HEADERS });
}
