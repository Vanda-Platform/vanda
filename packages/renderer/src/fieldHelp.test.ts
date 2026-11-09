import { describe, expect, it } from 'vitest';
import { describedBy, helpPlace, repeaterHints } from './fieldHelp';

describe('helpPlace', () => {
  it('puts the hint of a labelled field beside its label, of an unlabelled one beside the field', () => {
    expect(helpPlace({ help: 'Latin letters', label: 'Slug' })).toBe('label');
    expect(helpPlace({ help: 'Latin letters' })).toBe('beside');
  });

  it('keeps it as text with inline, leaves it to the column header for a hidden label', () => {
    expect(helpPlace({ help: '10 or 12 digits', helpInline: true, label: 'INN' })).toBe('inline');
    expect(helpPlace({ help: 'Grams', label: 'Weight', labelHidden: true, helpInline: true })).toBe('column');
    expect(helpPlace({ label: 'Name' })).toBe('none');
  });
});

describe('describedBy', () => {
  it('names the error first, then the hint', () => {
    expect(describedBy('f', 'Required', 'Latin letters')).toBe('f-error f-help');
    expect(describedBy('f', undefined, 'Latin letters')).toBe('f-help');
    expect(describedBy('f', undefined, undefined)).toBeUndefined();
  });
});

describe('repeaterHints', () => {
  const templates = [
    { path: 'name', help: 'As on the pack' },
    { path: 'value', help: 'With the unit', helpInline: true },
  ];

  it('gives table columns their own «i» and keeps inline hints under the list', () => {
    expect(repeaterHints('table', { help: 'Shown on the product page' }, templates)).toEqual({
      label: ['Shown on the product page'],
      columns: { name: 'As on the pack' },
      inline: ['With the unit'],
    });
  });

  it('joins the hint of a list field to the Repeater, leaves block fields their own', () => {
    expect(repeaterHints('list', {}, [templates[0]!])).toEqual({ label: ['As on the pack'], columns: {}, inline: [] });
    expect(repeaterHints('blocks', { help: 'Rows', helpInline: true }, templates)).toEqual({ label: [], columns: {}, inline: ['Rows'] });
  });
});
