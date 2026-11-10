/**
 * Input::suggestions(): what the list under a field offers and which value the arrows highlight.
 */

/** The server's values as sent, without the one already in the field. */
export function offeredSuggestions(values: readonly string[] | null | undefined, current: string): string[] {
  return (values ?? []).filter((value) => value !== current);
}

/**
 * The highlighted index after ↓ (step 1) or ↑ (step -1): from nothing, ↓ takes the first and ↑ the
 * last; past either end it wraps around. -1 when there is nothing to highlight.
 */
export function nextSuggestion(current: number, step: 1 | -1, count: number): number {
  if (count <= 0) return -1;
  if (current < 0 || current >= count) return step > 0 ? 0 : count - 1;
  return (current + step + count) % count;
}
