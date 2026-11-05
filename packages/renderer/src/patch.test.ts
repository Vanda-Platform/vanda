import { describe, expect, it } from 'vitest';
import { applyPatches } from './patch';
import type { Page } from './types';

const page = {
  schema: 'vanda/v1',
  screen: 'S',
  url: '/s',
  title: null,
  description: null,
  commandBar: [],
  layout: [
    {
      type: 'grid',
      id: 'layout.0',
      children: [
        { type: 'select', id: 'form.city', path: 'form.city', options: [{ value: 1, label: 'Moscow' }] },
        { type: 'input', id: 'form.vat', path: 'form.vat' },
      ],
    },
  ],
  state: { form: { country: 'RU', city: 1 } },
  snapshot: 'x',
  app: { name: 'Vanda', brand: '#12805C', menu: [], user: null },
} satisfies Page;

describe('applyPatches', () => {
  it('replaces a nested node and a state value', () => {
    const next = applyPatches(page, [
      { op: 'replace', path: '/nodes/form.city', value: { type: 'select', id: 'form.city', path: 'form.city', options: [{ value: 20, label: 'Paris' }] } },
      { op: 'replace', path: '/state/form/city', value: null },
    ]);

    expect(next.layout[0]?.children?.[0]?.options).toEqual([{ value: 20, label: 'Paris' }]);
    expect(next.state).toEqual({ form: { country: 'RU', city: null } });
    expect(page.state.form.city).toBe(1);
  });

  it('removes a node when the value is null', () => {
    const next = applyPatches(page, [{ op: 'replace', path: '/nodes/form.vat', value: null }]);

    expect(next.layout[0]?.children?.map((n) => n.id)).toEqual(['form.city']);
  });
});
