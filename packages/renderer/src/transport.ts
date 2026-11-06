import type { ActionRef, ActionResponse, Option, Page, ReactResponse, State } from './types';

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

/** Records of a Relation field matching the typed text (Relation::search() on the server). */
export function options(page: Page, field: string, query: string, signal?: AbortSignal) {
  return post<{ options: Option[] }>(page, { type: 'options', snapshot: page.snapshot, state: page.state, field, query }, signal);
}

export function react(page: Page, state: State, signal: AbortSignal) {
  return post<ReactResponse>(page, { type: 'react', snapshot: page.snapshot, state }, signal);
}

/**
 * Runs an #[Action]. Bulk actions get the selected ids; actions signed with
 * ActionCall::withInput() get the named values the browser supplies (a dragged row).
 */
export function act(page: Page, node: string, action: ActionRef, ids?: string[], input?: Record<string, unknown>) {
  return post<ActionResponse>(page, {
    type: 'action',
    action: action.name,
    args: action.bulk ? { ids: ids ?? [] } : action.inputs ? (input ?? {}) : (action.args ?? {}),
    bulk: action.bulk ?? false,
    inputs: action.inputs,
    node,
    token: action.token,
    snapshot: page.snapshot,
    state: page.state,
  });
}

export interface UploadResult {
  /** Missing for a URL stored before Vanda with no attachment behind it. */
  id?: number;
  name: string;
  size?: number | null;
  mime: string;
  extension?: string | null;
  url?: string | null;
  /** What the field keeps: the id or the URL (Upload::target()). */
  value: number | string;
}

/**
 * Sends a file to an Upload field of the screen. Uses XMLHttpRequest because fetch
 * cannot report upload progress. Returns the stored attachment or the field's errors.
 */
export function upload(
  page: Page,
  field: string,
  file: File,
  onProgress: (fraction: number) => void,
): { promise: Promise<{ status: number; data: UploadResult & { errors?: Record<string, string[]> } }>; abort: () => void } {
  const xhr = new XMLHttpRequest();
  const body = new FormData();
  body.append('type', 'upload');
  body.append('field', field);
  body.append('snapshot', page.snapshot);
  body.append('file', file);

  const promise = new Promise<{ status: number; data: UploadResult & { errors?: Record<string, string[]> } }>((resolve, reject) => {
    xhr.open('POST', page.url + window.location.search);
    xhr.setRequestHeader('Accept', 'application/json');
    xhr.setRequestHeader('X-Vanda', 'true');
    for (const [name, value] of Object.entries(csrfHeaders())) xhr.setRequestHeader(name, value);
    xhr.upload.onprogress = (event) => event.lengthComputable && onProgress(event.loaded / event.total);
    xhr.onload = () => {
      try {
        resolve({ status: xhr.status, data: JSON.parse(xhr.responseText) });
      } catch {
        reject(new Error(`Vanda upload failed (${xhr.status})`));
      }
    };
    xhr.onerror = () => reject(new Error('Vanda upload failed: network error'));
    xhr.onabort = () => reject(new DOMException('Upload cancelled', 'AbortError'));
    xhr.send(body);
  });

  return { promise, abort: () => xhr.abort() };
}
