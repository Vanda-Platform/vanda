/**
 * The Repeater's bookkeeping, kept apart from React so it can be tested: row keys that follow their
 * rows, how fields sit in a block, the words that name a folded block.
 */

/**
 * Keys that move, appear and go with their rows, so a row's editors, uploads and folds move with it
 * instead of being rebuilt in another row's place. Rows that change from outside (a reaction, a
 * refresh with another length) get new keys at the end or lose the last ones.
 */
export class RowKeys {
  private keys: string[] = [];
  private counter = 0;

  /** The keys for `count` rows. */
  list(count: number): string[] {
    while (this.keys.length < count) this.keys.push(this.fresh());
    if (this.keys.length > count) this.keys = this.keys.slice(0, count);
    return this.keys;
  }

  move(from: number, to: number): void {
    const [key] = this.keys.splice(from, 1);
    if (key !== undefined) this.keys.splice(to, 0, key);
  }

  /** A new row at `at`; returns its key. */
  insert(at: number): string {
    const key = this.fresh();
    this.keys.splice(at, 0, key);
    return key;
  }

  remove(at: number): void {
    this.keys.splice(at, 1);
  }

  /** The keys as they were (a removed row came back). */
  restore(keys: string[]): void {
    this.keys = [...keys];
  }

  snapshot(): string[] {
    return [...this.keys];
  }

  private fresh(): string {
    return `row-${++this.counter}`;
  }
}

/** Fields that need the whole width of a block. */
export const WIDE_FIELDS = new Set(['richtext', 'tinymce', 'markdown', 'code', 'textarea', 'repeater', 'keyvalue', 'map', 'permissions', 'matrix']);

/**
 * Which fields of a block take its whole width: wide ones always; short ones pair up in two columns,
 * and one left without a pair (before a wide field, or the last) takes the row instead of half of it.
 */
export function fullWidth(types: string[]): boolean[] {
  const full = types.map((type) => WIDE_FIELDS.has(type));
  let run = 0;
  types.forEach((_, index) => {
    if (full[index]) {
      if (run % 2 === 1) full[index - 1] = true;
      run = 0;
    } else {
      run++;
    }
  });
  if (run % 2 === 1) full[types.length - 1] = true;
  return full;
}

interface Template {
  type: string;
  path: string;
  options?: { value: string | number; label: string }[];
}

const TEXT = new Set(['input', 'select', 'relation', 'datetimer', 'label']);
const LONG_TEXT = new Set(['textarea', 'richtext', 'tinymce', 'markdown']);

/** Visible text of HTML, read in an inert document (no scripts run, nothing loads). */
function plain(html: string): string {
  if (!/[<&]/.test(html)) return html;
  if (typeof DOMParser === 'undefined') return html.replace(/<[^>]*>/g, ' ');
  return new DOMParser().parseFromString(html, 'text/html').body.textContent ?? '';
}

/**
 * The words that name a row in its block's header: the value of its first text field (a select's
 * option label), else the beginning of its text; '' when the row is still empty.
 */
export function rowSummary(row: Record<string, unknown> | undefined, templates: Template[], max = 90): string {
  if (!row) return '';
  const read = (template: Template): string => {
    const value = template.path.split('.').reduce<unknown>((current, part) => (current !== null && typeof current === 'object' ? (current as Record<string, unknown>)[part] : undefined), row);
    if (value === null || value === undefined || value === '' || typeof value === 'object') return '';
    const option = template.options?.find((candidate) => String(candidate.value) === String(value));
    return option ? option.label : String(value);
  };
  const found = templates.filter((template) => TEXT.has(template.type)).map(read).find(Boolean) ?? templates.filter((template) => LONG_TEXT.has(template.type)).map((template) => plain(read(template))).find((text) => text.trim() !== '') ?? '';
  const text = found.replace(/\s+/g, ' ').trim();
  return text.length > max ? `${text.slice(0, max - 1).trimEnd()}…` : text;
}
