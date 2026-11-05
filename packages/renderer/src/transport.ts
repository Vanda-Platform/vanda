import type { ActionRef, ActionResponse, Page, ReactResponse, State } from './types';

/**
 * The only module that talks to the server. Inertia will replace navigation here
 * later; reactions and actions keep this JSON protocol.
 */
function csrf(): string {
  return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
}

async function post<T>(url: string, body: unknown, signal?: AbortSignal): Promise<{ status: number; data: T }> {
  const response = await fetch(url, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      'X-CSRF-TOKEN': csrf(),
      'X-Vanda': 'true',
    },
    credentials: 'same-origin',
    body: JSON.stringify(body),
    signal,
  });

  if (response.status === 409 || response.status === 419) {
    // Snapshot or CSRF token is stale (e.g. after a deploy): start over with a fresh page.
    window.location.reload();
    throw new Error('Page state expired');
  }

  if (!response.ok && response.status !== 422) {
    const text = await response.text();
    throw new Error(`Vanda request failed (${response.status}): ${text.slice(0, 300)}`);
  }

  return { status: response.status, data: (await response.json()) as T };
}

export function react(page: Page, state: State, signal: AbortSignal) {
  return post<ReactResponse>(page.url, { type: 'react', snapshot: page.snapshot, state }, signal);
}

export function act(page: Page, action: ActionRef) {
  return post<ActionResponse>(page.url, {
    type: 'action',
    action: action.name,
    args: action.args ?? {},
    token: action.token,
    snapshot: page.snapshot,
    state: page.state,
  });
}
