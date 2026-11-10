import { describe, expect, it } from 'vitest';
import { nextSuggestion, offeredSuggestions } from './suggestions';

describe('offeredSuggestions', () => {
  it('keeps the server order and every value, matching the typed text or not', () => {
    expect(offeredSuggestions(['г Москва, ул Тверская, д 1', 'г Москва, ул Тверская, д 1 стр 2'], 'Москва Тверская 1')).toEqual([
      'г Москва, ул Тверская, д 1',
      'г Москва, ул Тверская, д 1 стр 2',
    ]);
  });

  it('leaves out the value already in the field', () => {
    expect(offeredSuggestions(['a', 'b'], 'a')).toEqual(['b']);
  });

  it('offers nothing without a list', () => {
    expect(offeredSuggestions(undefined, 'x')).toEqual([]);
    expect(offeredSuggestions(null, 'x')).toEqual([]);
  });
});

describe('nextSuggestion', () => {
  it('starts at the first with ↓ and at the last with ↑', () => {
    expect(nextSuggestion(-1, 1, 3)).toBe(0);
    expect(nextSuggestion(-1, -1, 3)).toBe(2);
  });

  it('moves and wraps around', () => {
    expect(nextSuggestion(0, 1, 3)).toBe(1);
    expect(nextSuggestion(2, 1, 3)).toBe(0);
    expect(nextSuggestion(0, -1, 3)).toBe(2);
  });

  it('restarts when the list got shorter and highlights nothing in an empty one', () => {
    expect(nextSuggestion(5, 1, 2)).toBe(0);
    expect(nextSuggestion(0, 1, 0)).toBe(-1);
  });
});
