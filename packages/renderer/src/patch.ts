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
  const [head, ...rest] = path.split('.') as [string, ...string[]];
  if (rest.length === 0) return { ...state, [head]: value };

  const child = state[head];
  const base = child !== null && typeof child === 'object' && !Array.isArray(child) ? (child as State) : {};
  return { ...state, [head]: setPath(base, rest.join('.'), value) };
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
