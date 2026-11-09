/**
 * The hidden columns of a table. For a signed-in user the server keeps them (Table::columnChoice is
 * 'saved' or 'none') and draws the table with them; a guest's stay in the browser (localStorage),
 * as before Vanda kept them on the server.
 */
export type ColumnChoice = 'saved' | 'none' | undefined;

/** Where the browser kept a table's hidden columns: per page address and table id. */
export const localColumnsKey = (path: string, table: string) => `vanda:columns:${path}:${table}`;

export function readLocalColumns(key: string): string[] | null {
  try {
    const saved = localStorage.getItem(key);
    const parsed: unknown = saved ? JSON.parse(saved) : null;
    return Array.isArray(parsed) ? parsed.filter((name): name is string => typeof name === 'string') : null;
  } catch {
    return null;
  }
}

export function writeLocalColumns(key: string, hidden: string[] | null): void {
  try {
    if (hidden === null) localStorage.removeItem(key);
    else localStorage.setItem(key, JSON.stringify(hidden));
  } catch {
    // Private mode or blocked storage: applied, not remembered.
  }
}

/**
 * The columns to hide at first and whether to hand the browser's old choice to the server: a user
 * who chose on the server sees that; one who did not yet, but chose in this browser before, keeps
 * that choice, sent once (`migrate`); a guest keeps the browser's.
 */
export function initialHidden(choice: ColumnChoice, serverHidden: string[], local: string[] | null): { hidden: string[]; migrate: boolean } {
  if (choice === 'saved') return { hidden: serverHidden, migrate: false };
  if (local === null) return { hidden: serverHidden, migrate: false };
  return { hidden: local, migrate: choice === 'none' };
}
