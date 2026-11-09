import { describe, expect, it } from 'vitest';
import { moveTile, storedLayout, type TileBox } from './filesNav';

// Three tiles in the first row, two in the second (a grid), then a section below with one tile.
const grid: TileBox[] = [
  { left: 0, top: 0, width: 100 },
  { left: 110, top: 0, width: 100 },
  { left: 220, top: 0, width: 100 },
  { left: 0, top: 120, width: 100 },
  { left: 110, top: 120, width: 100 },
  { left: 0, top: 300, width: 100 },
];

describe('moveTile', () => {
  it('walks tiles in reading order with ← and →, to the ends with Home and End', () => {
    expect(moveTile(grid, 2, 'ArrowRight')).toBe(3);
    expect(moveTile(grid, 5, 'ArrowRight')).toBeNull();
    expect(moveTile(grid, 0, 'ArrowLeft')).toBeNull();
    expect(moveTile(grid, 3, 'Home')).toBe(0);
    expect(moveTile(grid, 3, 'End')).toBe(5);
    expect(moveTile(grid, 3, 'Enter')).toBeNull();
  });

  it('goes to the nearest tile of the row above or below with ↑ and ↓', () => {
    expect(moveTile(grid, 1, 'ArrowDown')).toBe(4);
    // No tile under the third column: the nearest one of the next row.
    expect(moveTile(grid, 2, 'ArrowDown')).toBe(4);
    expect(moveTile(grid, 4, 'ArrowDown')).toBe(5);
    expect(moveTile(grid, 4, 'ArrowUp')).toBe(1);
    expect(moveTile(grid, 0, 'ArrowUp')).toBeNull();
  });

  it('moves row by row in a list', () => {
    const list: TileBox[] = [0, 44, 88].map((top) => ({ left: 0, top, width: 600 }));
    expect(moveTile(list, 0, 'ArrowDown')).toBe(1);
    expect(moveTile(list, 2, 'ArrowUp')).toBe(1);
  });
});

describe('storedLayout', () => {
  it('renders without storage (tests, private mode)', () => {
    expect(storedLayout('files')).toBeNull();
  });
});
