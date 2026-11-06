import type { Page, PatchOp, State, UiNode } from './types';

/**
 * Applies reaction patches: "/nodes/<id>" replaces one node (null removes it),
 * "/state/<a>/<b>" sets a state value. Mirrors Vanda\Support\Patch.
 */
export function applyPatches(page: Page, patches: PatchOp[]): Page {
  let next = page;

  for (const patch of patches) {
    const [root, ...segments] = patch.path
      .replace(/^\//, '')
      .split('/')
      .map((s) => s.replace(/~1/g, '/').replace(/~0/g, '~'));

    if (root === 'nodes') {
      const id = segments.join('/');
      const value = patch.value as UiNode | null;
      next = {
        ...next,
        commandBar: replaceNode(next.commandBar, id, value),
        layout: replaceNode(next.layout, id, value),
      };
    } else if (root === 'state') {
      next = { ...next, state: setPath(next.state, segments.join('.'), patch.value) };
    }
  }

  return next;
}

export function setPath(state: State, path: string, value: unknown): State {
  return setIn(state, path.split('.'), value) as State;
}

/** Immutable set that keeps lists as lists: "form.rows.1.title" changes one row, not the whole list. */
function setIn(container: unknown, keys: string[], value: unknown): unknown {
  const [head, ...rest] = keys as [string, ...string[]];
  const copy: Record<string, unknown> | unknown[] = Array.isArray(container)
    ? [...container]
    : container !== null && typeof container === 'object'
      ? { ...(container as Record<string, unknown>) }
      : /^\d+$/.test(head)
        ? []
        : {};
  (copy as Record<string, unknown>)[head] = rest.length === 0 ? value : setIn((copy as Record<string, unknown>)[head], rest, value);
  return copy;
}

function replaceNode(nodes: UiNode[], id: string, replacement: UiNode | null): UiNode[] {
  return nodes.flatMap((node) => {
    if (node.id === id) return replacement === null ? [] : [replacement];
    if (node.children) return [{ ...node, children: replaceNode(node.children, id, replacement) }];
    return [node];
  });
}

/** Every node of the page, depth-first. */
export function walk(nodes: UiNode[]): UiNode[] {
  return nodes.flatMap((node) => [node, ...walk(node.children ?? [])]);
}
