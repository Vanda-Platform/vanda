import { describe, expect, it } from 'vitest';
import { DARK_INK, onAccent } from './color';

describe('onAccent', () => {
  it('keeps white text on a dark or saturated brand', () => {
    expect(onAccent('#12805C')).toBe('#ffffff');
    expect(onAccent('#2563eb')).toBe('#ffffff');
    expect(onAccent('#c81e1e')).toBe('#ffffff');
  });

  it('turns the text dark on a light brand, where white would not read', () => {
    expect(onAccent('#FFD60A')).toBe(DARK_INK);
    expect(onAccent('#7dd3fc')).toBe(DARK_INK);
    expect(onAccent('#fff')).toBe(DARK_INK);
  });

  it('keeps white for colours it cannot read', () => {
    expect(onAccent('oklch(0.6 0.15 160)')).toBe('#ffffff');
    expect(onAccent('green')).toBe('#ffffff');
  });
});
