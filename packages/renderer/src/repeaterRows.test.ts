import { describe, expect, it } from 'vitest';
import { fullWidth, RowKeys, rowSummary } from './repeaterRows';

describe('Repeater rows', () => {
  it('keep their keys when they move, appear and go', () => {
    const keys = new RowKeys();
    const [a, b, c] = keys.list(3);
    keys.move(0, 2);
    expect(keys.list(3)).toEqual([b, c, a]);

    const before = keys.snapshot();
    keys.remove(1);
    expect(keys.list(2)).toEqual([b, a]);
    keys.restore(before);
    expect(keys.list(3)).toEqual([b, c, a]);

    const added = keys.insert(1);
    expect(keys.list(4)).toEqual([b, added, c, a]);
    expect(new Set(keys.list(4)).size).toBe(4);
    // Rows from outside: fewer of them keep the first keys, more get new ones.
    expect(keys.list(2)).toEqual([b, added]);
    expect(keys.list(3)[2]).not.toBe(c);
  });

  it('pair short fields in a block and give a lone one the whole row', () => {
    expect(fullWidth(['input', 'tinymce'])).toEqual([true, true]);
    expect(fullWidth(['input', 'upload'])).toEqual([false, false]);
    expect(fullWidth(['input', 'select', 'input'])).toEqual([false, false, true]);
    expect(fullWidth(['input', 'input', 'textarea', 'datetimer'])).toEqual([false, false, true, true]);
  });

  it('name a block by its first text field, else by the beginning of its text', () => {
    const templates = [
      { type: 'upload', path: 'image' },
      { type: 'select', path: 'kind', options: [{ value: 'faq', label: 'Вопрос и ответ' }] },
      { type: 'input', path: 'title' },
      { type: 'tinymce', path: 'text' },
    ];
    expect(rowSummary({ title: '  Доставка  ', kind: 'faq' }, templates)).toBe('Вопрос и ответ');
    expect(rowSummary({ title: 'Доставка' }, templates)).toBe('Доставка');
    expect(rowSummary({ text: '<p>Бесплатно от <strong>3 000 ₽</strong></p>' }, templates.slice(3))).toBe('Бесплатно от 3 000 ₽');
    expect(rowSummary({ meta: { title: 'Вложенное' } }, [{ type: 'input', path: 'meta.title' }])).toBe('Вложенное');
    expect(rowSummary({}, templates)).toBe('');
    expect(rowSummary({ title: 'x'.repeat(100) }, templates, 10)).toBe(`${'x'.repeat(9)}…`);
  });
});
