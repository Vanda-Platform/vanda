import { describe, expect, it } from 'vitest';
import { applyMove, dropMove, hasBranches, keyboardMove, type TreeItem } from './treeMove';

// home, about (team, careers), blog
const items: TreeItem[] = [
  { key: 'home', parent: null, depth: 0 },
  { key: 'about', parent: null, depth: 0 },
  { key: 'team', parent: 'about', depth: 1 },
  { key: 'careers', parent: 'about', depth: 1 },
  { key: 'blog', parent: null, depth: 0 },
];

const shape = (list: TreeItem[]) => list.map((item) => `${'-'.repeat(item.depth)}${item.key}`).join(' ');

describe('tree moves', () => {
  it('drops before, after and inside a row', () => {
    expect(dropMove(items, 'blog', items[2]!, 'before')).toEqual({ key: 'blog', parent: 'about', position: 0 });
    expect(dropMove(items, 'home', items[4]!, 'after')).toEqual({ key: 'home', parent: null, position: 2 });
    expect(dropMove(items, 'home', items[3]!, 'inside')).toEqual({ key: 'home', parent: 'careers', position: 0 });
    expect(dropMove(items, 'about', items[2]!, 'inside')).toBeNull();
  });

  it('rebuilds the display order with depths', () => {
    expect(shape(applyMove(items, { key: 'blog', parent: 'about', position: 1 }))).toBe('home about -team -blog -careers');
    expect(shape(applyMove(items, { key: 'about', parent: 'home', position: 0 }))).toBe('home -about --team --careers blog');
  });

  it('moves with the keyboard', () => {
    expect(keyboardMove(items, 'careers', 'up')).toEqual({ key: 'careers', parent: 'about', position: 0 });
    expect(keyboardMove(items, 'careers', 'down')).toBeNull();
    expect(keyboardMove(items, 'blog', 'in')).toEqual({ key: 'blog', parent: 'about', position: 2 });
    expect(keyboardMove(items, 'team', 'out')).toEqual({ key: 'team', parent: null, position: 2 });
    expect(shape(applyMove(items, keyboardMove(items, 'team', 'out')!))).toBe('home about -careers team blog');
  });

  it('tells a tree from a flat list, also after a move nests or un-nests a row', () => {
    const flat: TreeItem[] = [
      { key: 'a', parent: null, depth: 0 },
      { key: 'b', parent: null, depth: 0 },
    ];

    expect(hasBranches(items)).toBe(true);
    expect(hasBranches(flat)).toBe(false);
    expect(hasBranches([])).toBe(false);
    expect(hasBranches(applyMove(flat, { key: 'b', parent: 'a', position: 0 }))).toBe(true);
    expect(hasBranches(applyMove(applyMove(flat, { key: 'b', parent: 'a', position: 0 }), { key: 'b', parent: null, position: 1 }))).toBe(false);
  });
});
