import { describe, expect, it } from 'vitest';
import { initialHidden, readLocalColumns } from './tableColumns';

describe('initialHidden', () => {
  it('shows what the server keeps for a user who chose', () => {
    expect(initialHidden('saved', ['email'], ['country'])).toEqual({ hidden: ['email'], migrate: false });
  });

  it('hands the browser\'s old choice to the server once, for a user who has none there yet', () => {
    expect(initialHidden('none', ['country'], ['email', 'roles'])).toEqual({ hidden: ['email', 'roles'], migrate: true });
    expect(initialHidden('none', ['country'], null)).toEqual({ hidden: ['country'], migrate: false });
  });

  it('keeps a guest\'s choice in the browser', () => {
    expect(initialHidden(undefined, ['country'], ['email'])).toEqual({ hidden: ['email'], migrate: false });
    expect(initialHidden(undefined, ['country'], null)).toEqual({ hidden: ['country'], migrate: false });
  });
});

describe('readLocalColumns', () => {
  it('reads nothing without storage', () => {
    expect(readLocalColumns('vanda:columns:/admin/users:layout.0')).toBeNull();
  });
});
