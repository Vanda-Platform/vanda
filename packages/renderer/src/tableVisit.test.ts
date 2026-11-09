import { beforeEach, describe, expect, it, vi } from 'vitest';

const visit = vi.fn();
vi.mock('@inertiajs/react', () => ({ router: { visit: (...args: unknown[]) => visit(...args) } }));

const { TABLE_VISIT_HEADERS, urlWith, visitWith } = await import('./tableVisit');

describe('table visits', () => {
  beforeEach(() => {
    visit.mockClear();
    vi.stubGlobal('window', { location: { href: 'https://panel.test/admin/users?tab=2&filter[status]=blocked&page=3' } });
  });

  it('mark themselves, so the server remembers the view they lead to (an empty one after «Reset» too)', () => {
    visitWith({}, {});

    expect(visit).toHaveBeenCalledWith('https://panel.test/admin/users?tab=2', { preserveScroll: true, preserveState: true, replace: true, headers: TABLE_VISIT_HEADERS });
    expect(TABLE_VISIT_HEADERS).toEqual({ 'X-Vanda-Table': '1' });
  });

  it('change only the parameters given; a new filter starts on page 1', () => {
    expect(decodeURIComponent(urlWith({ perPage: '50' }))).toBe('https://panel.test/admin/users?tab=2&filter[status]=blocked&page=3&perPage=50');
    expect(decodeURIComponent(urlWith({}, { status: 'active', created: { start: '2026-01-01', end: null } }))).toBe(
      'https://panel.test/admin/users?tab=2&filter[status]=active&filter[created][start]=2026-01-01',
    );
  });
});
