import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { describe, expect, it } from 'vitest';

describe('TinyMCE plugins', () => {
  it('are the list PHP checks screens against (packages/core/resources/tinymce-plugins.json)', () => {
    const json = fileURLToPath(new URL('../../../core/resources/tinymce-plugins.json', import.meta.url));
    const php = JSON.parse(readFileSync(json, 'utf8')) as string[];
    const source = readFileSync(fileURLToPath(new URL('./tinymce-plugins.ts', import.meta.url)), 'utf8');
    const bundled = [...source.matchAll(/^import 'tinymce\/plugins\/([a-z]+)';$/gm)].map((match) => match[1]);

    // A plugin imported in tinymce-plugins.ts goes into the JSON too (sorted), or PHP rejects the screens that use it.
    expect(php).toEqual(bundled);
    expect([...php].sort()).toEqual(php);
  });

  it('stay on TinyMCE 6, the last MIT version', () => {
    const lock = JSON.parse(readFileSync(fileURLToPath(new URL('../../../../node_modules/tinymce/package.json', import.meta.url)), 'utf8')) as { version: string; license: string };

    expect(lock.version).toMatch(/^6\./);
    expect(lock.license).toBe('MIT');
  });
});
