/** Moving rows of an adjacency-list tree that is kept as a flat list in display order. */

export interface TreeItem {
  key: string;
  parent?: string | null;
  depth: number;
}

export interface Move {
  key: string;
  parent: string | null;
  /** 0-based index among the new siblings, not counting the moved item. */
  position: number;
}

const parentOf = (item: TreeItem) => item.parent ?? null;

export function siblings<T extends TreeItem>(items: T[], parent: string | null, except?: string): T[] {
  return items.filter((item) => parentOf(item) === parent && item.key !== except);
}

export function hasChildren(items: TreeItem[], key: string): boolean {
  return items.some((item) => parentOf(item) === key);
}

/** The key and every key below it. */
export function branch(items: TreeItem[], key: string): Set<string> {
  const keys = new Set([key]);
  for (const item of items) if (item.parent && keys.has(item.parent)) keys.add(item.key);
  return keys;
}

/** The list after a move, in display order with depths recomputed. */
export function applyMove<T extends TreeItem>(items: T[], move: Move): T[] {
  const moved = items.find((item) => item.key === move.key);
  if (!moved || (move.parent !== null && branch(items, move.key).has(move.parent))) return items;

  const children = new Map<string | null, T[]>();
  for (const item of items) {
    if (item.key === move.key) continue;
    const list = children.get(parentOf(item)) ?? [];
    list.push(item);
    children.set(parentOf(item), list);
  }
  const target = children.get(move.parent) ?? [];
  target.splice(Math.max(0, Math.min(move.position, target.length)), 0, { ...moved, parent: move.parent });
  children.set(move.parent, target);

  const result: T[] = [];
  const walk = (parent: string | null, depth: number) => {
    for (const item of children.get(parent) ?? []) {
      result.push({ ...item, depth });
      walk(item.key, depth + 1);
    }
  };
  walk(null, 0);
  return result;
}

export type DropZone = 'before' | 'after' | 'inside';

/** Where a row lands when dropped on `target`. Null when the drop makes no sense. */
export function dropMove(items: TreeItem[], key: string, target: TreeItem, zone: DropZone): Move | null {
  if (branch(items, key).has(target.key)) return null;

  if (zone === 'inside') return { key, parent: target.key, position: siblings(items, target.key, key).length };

  const parent = parentOf(target);
  const index = siblings(items, parent, key).findIndex((item) => item.key === target.key);
  return { key, parent, position: zone === 'before' ? index : index + 1 };
}

/** Alt+arrows: up/down among siblings, right into the previous sibling, left out of the parent. */
export function keyboardMove(items: TreeItem[], key: string, direction: 'up' | 'down' | 'in' | 'out'): Move | null {
  const item = items.find((entry) => entry.key === key);
  if (!item) return null;
  const parent = parentOf(item);
  const list = siblings(items, parent);
  const index = list.findIndex((entry) => entry.key === key);

  switch (direction) {
    case 'up':
      return index > 0 ? { key, parent, position: index - 1 } : null;
    case 'down':
      return index < list.length - 1 ? { key, parent, position: index + 1 } : null;
    case 'in': {
      const previous = list[index - 1];
      return previous ? { key, parent: previous.key, position: siblings(items, previous.key).length } : null;
    }
    case 'out': {
      const parentItem = items.find((entry) => entry.key === parent);
      if (!parentItem) return null;
      const grand = parentOf(parentItem);
      return { key, parent: grand, position: siblings(items, grand, key).findIndex((entry) => entry.key === parentItem.key) + 1 };
    }
  }
}
