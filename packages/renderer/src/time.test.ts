import { describe, expect, it } from 'vitest';
import { relativeTime } from './time';

const now = Date.parse('2026-11-06T12:00:00Z');
const ago = (seconds: number) => new Date(now - seconds * 1000).toISOString();

describe('relative time of activity entries', () => {
  it('says how long ago, in the language of the panel', () => {
    expect(relativeTime(ago(10), 'ru', now)).toBe('сейчас');
    expect(relativeTime(ago(5 * 60), 'ru', now)).toBe('5 минут назад');
    expect(relativeTime(ago(3 * 3600), 'en', now)).toBe('3 hours ago');
    expect(relativeTime(ago(86400), 'ru', now)).toBe('вчера');
  });

  it('leaves a week and more to the date', () => {
    expect(relativeTime(ago(8 * 86400), 'ru', now)).toBeNull();
    expect(relativeTime(undefined, 'ru', now)).toBeNull();
  });
});
