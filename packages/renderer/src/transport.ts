import type { ActionRef, ActionResponse, Page, ReactResponse, State } from './types';

/**
 * Reactions and actions: Vanda's JSON protocol on the screen's own URL.
 * Navigation between screens goes through Inertia instead.
 */
function csrfHeaders(): Record<string, string> {
  // The XSRF-TOKEN cookie is refreshed by Laravel on every response (e.g. after sign-in),
  // unlike the meta tag, which only exists in the first HTML page.
  const cookie = document.cookie.split('; ').find((part) => part.startsWith('XSRF-TOKEN='));
  if (cookie) return { 'X-XSRF-TOKEN': decodeURIComponent(cookie.slice('XSRF-TOKEN='.length)) };

  const meta = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content;
  return meta ? { 'X-CSRF-TOKEN': meta } : {};
}

async function post<T>(page: Page, body: unknown, signal?: AbortSignal): Promise<{ status: number; data: T }> {
  // Keep ?page=2&sort=… so tables re-render with the same view after an action.
  const response = await fetch(page.url + window.location.search, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-Vanda': 'true', ...csrfHeaders() },
    credentials: 'same-origin',
    body: JSON.stringify(body),
    signal,
  });

  if (response.status === 401 || response.status === 409 || response.status === 419) {
    // Signed out, stale snapshot or CSRF token: start over with a fresh page.
    window.location.reload();
    throw new Error(`Vanda page expired (${response.status})`);
  }

  if (!response.ok && response.status !== 422) {
    const text = await response.text();
    throw new Error(`Vanda request failed (${response.status}): ${text.slice(0, 300)}`);
  }

  return { status: response.status, data: (await response.json()) as T };
}

export function react(page: Page, state: State, signal: AbortSignal) {
  return post<ReactResponse>(page, { type: 'react', snapshot: page.snapshot, state }, signal);
}

export function act(page: Page, node: string, action: ActionRef, ids?: string[]) {
  return post<ActionResponse>(page, {
    type: 'action',
    action: action.name,
    args: action.bulk ? { ids: ids ?? [] } : (action.args ?? {}),
    bulk: action.bulk ?? false,
    node,
    token: action.token,
    snapshot: page.snapshot,
    state: page.state,
  });
}
