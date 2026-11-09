/** Where a tile of Layout::files() sits on the screen (its bounding box). */
export interface TileBox {
  left: number;
  top: number;
  width: number;
}

/**
 * The tile the arrow keys move to from tile `index`: ← → the previous and next one in reading order,
 * ↑ ↓ the nearest tile of the row above or below (tiles in a grid, rows in a list), Home and End
 * the first and the last. Null when there is nowhere to go or the key is not one of these.
 */
export function moveTile(tiles: TileBox[], index: number, key: string): number | null {
  const current = tiles[index];
  if (!current) return null;

  switch (key) {
    case 'ArrowRight':
      return index + 1 < tiles.length ? index + 1 : null;
    case 'ArrowLeft':
      return index > 0 ? index - 1 : null;
    case 'Home':
      return index > 0 ? 0 : null;
    case 'End':
      return index < tiles.length - 1 ? tiles.length - 1 : null;
    case 'ArrowDown':
    case 'ArrowUp': {
      const down = key === 'ArrowDown';
      const others = tiles.map((tile, i) => ({ tile, i })).filter(({ tile }) => (down ? tile.top > current.top + 1 : tile.top < current.top - 1));
      if (others.length === 0) return null;
      // The nearest row first, then the tile in it whose middle is nearest to this one's.
      const row = down ? Math.min(...others.map(({ tile }) => tile.top)) : Math.max(...others.map(({ tile }) => tile.top));
      const middle = current.left + current.width / 2;
      const inRow = others.filter(({ tile }) => Math.abs(tile.top - row) <= 1);
      inRow.sort((a, b) => Math.abs(a.tile.left + a.tile.width / 2 - middle) - Math.abs(b.tile.left + b.tile.width / 2 - middle));
      return inRow[0]!.i;
    }
    default:
      return null;
  }
}

/** The tiles view the user picked for a browser, per node id; null when none (or storage is blocked). */
export function storedLayout(id: string): 'grid' | 'list' | null {
  try {
    const value = localStorage.getItem(`vanda:files:${id}`);
    return value === 'grid' || value === 'list' ? value : null;
  } catch {
    return null;
  }
}

export function storeLayout(id: string, layout: 'grid' | 'list'): void {
  try {
    localStorage.setItem(`vanda:files:${id}`, layout);
  } catch {
    // Private mode or blocked storage: the choice holds until the page is left.
  }
}
