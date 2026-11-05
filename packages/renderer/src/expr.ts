import type { ExprNode, State } from './types';

/**
 * Evaluates Vanda expressions in the browser. Mirrors Vanda\Schema\Expr::evaluate();
 * packages/schema/fixtures/expressions.json keeps both implementations identical.
 * It is an interpreter over data, never eval(), so strict CSP keeps working.
 */
export function evaluate(node: ExprNode, state: State): unknown {
  if ('field' in node) return getPath(state, node.field);
  if ('value' in node) return node.value;

  const args = node.args;
  const value = (i: number) => evaluate(args[i] ?? { value: null }, state);

  switch (node.op) {
    case 'eq':
      return equals(value(0), value(1));
    case 'neq':
      return !equals(value(0), value(1));
    case 'in': {
      const list = value(1);
      const needle = value(0);
      return Array.isArray(list) && list.some((item) => equals(item, needle));
    }
    case 'gt':
      return compare(value(0), value(1), (a, b) => a > b);
    case 'gte':
      return compare(value(0), value(1), (a, b) => a >= b);
    case 'lt':
      return compare(value(0), value(1), (a, b) => a < b);
    case 'lte':
      return compare(value(0), value(1), (a, b) => a <= b);
    case 'empty':
      return blank(value(0));
    case 'notEmpty':
      return !blank(value(0));
    case 'and':
      return args.map((_, i) => truthy(value(i))).every(Boolean);
    case 'or':
      return args.map((_, i) => truthy(value(i))).some(Boolean);
    case 'not':
      return !truthy(value(0));
    case 'slug':
      return slugify(String(value(0) ?? ''));
    case 'lower':
      return String(value(0) ?? '').toLowerCase();
    case 'concat':
      return args.map((_, i) => String(value(i) ?? '')).join('');
    default:
      return null;
  }
}

export function isTrue(node: ExprNode | undefined, state: State): boolean {
  return node === undefined ? true : truthy(evaluate(node, state));
}

export function truthy(value: unknown): boolean {
  return !(value === null || value === undefined || value === false || value === '' || value === 0 || (Array.isArray(value) && value.length === 0));
}

export function getPath(state: State, path: string): unknown {
  let current: unknown = state;
  for (const segment of path.split('.')) {
    if (current === null || typeof current !== 'object') return null;
    current = (current as Record<string, unknown>)[segment];
  }
  return current === undefined ? null : current;
}

function blank(value: unknown): boolean {
  return value === null || value === undefined || value === '' || value === false || (Array.isArray(value) && value.length === 0);
}

/** Numbers and numeric strings compare as numbers: "12" equals 12. */
function normalize(value: unknown): unknown {
  if (typeof value === 'string' && /^-?\d+(\.\d+)?$/.test(value)) return Number(value);
  return value === undefined ? null : value;
}

function equals(a: unknown, b: unknown): boolean {
  return normalize(a) === normalize(b);
}

function compare(a: unknown, b: unknown, test: (a: number, b: number) => boolean): boolean {
  const x = normalize(a);
  const y = normalize(b);
  return typeof x === 'number' && typeof y === 'number' && test(x, y);
}

const TRANSLIT: Record<string, string> = {
  а: 'a', б: 'b', в: 'v', г: 'g', д: 'd', е: 'e', ё: 'e', ж: 'zh', з: 'z', и: 'i', й: 'y',
  к: 'k', л: 'l', м: 'm', н: 'n', о: 'o', п: 'p', р: 'r', с: 's', т: 't', у: 'u', ф: 'f',
  х: 'h', ц: 'ts', ч: 'ch', ш: 'sh', щ: 'sch', ъ: '', ы: 'y', ь: '', э: 'e', ю: 'yu', я: 'ya',
};

function slugify(text: string): string {
  const latin = [...text.toLowerCase()].map((char) => TRANSLIT[char] ?? char).join('');
  return latin.replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
}
