import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { describe, expect, it } from 'vitest';
import { ICON_NAMES } from './ui';

describe('icon names', () => {
  it('are the list PHP checks screens against (packages/core/resources/icons.json)', () => {
    const file = fileURLToPath(new URL('../../core/resources/icons.json', import.meta.url));
    const php = JSON.parse(readFileSync(file, 'utf8')) as string[];

    // A new icon in ui.tsx goes into icons.json too (sorted), or PHP rejects the screens that use it.
    expect(php).toEqual([...ICON_NAMES]);
  });
});
