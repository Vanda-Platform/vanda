// Input::mask() in the browser; kept apart from the components so it can be tested alone.

const isSlot = (char: string) => char === '9' || char === 'a' || char === '*';
const fitsSlot = (slot: string, char: string) => (slot === '9' ? /\p{N}/u.test(char) : slot === 'a' ? /\p{L}/u.test(char) : /[\p{L}\p{N}]/u.test(char));

/**
 * Formats typed text by an Input::mask(): 9 a digit, a a letter, * either; the rest is typed
 * for the user. "9123456789" with "+7 (999) 999-99-99" gives "+7 (912) 345-67-89".
 */
export function applyMask(mask: string, input: string): string {
  let out = '';
  let index = 0;
  for (const char of mask) {
    if (index >= input.length) break;
    if (isSlot(char)) {
      while (index < input.length && !fitsSlot(char, input[index]!)) index++;
      if (index >= input.length) break;
      out += input[index++];
    } else {
      out += char;
      if (input[index] === char) index++;
    }
  }
  return out;
}

/** The next value of a masked input; a deleted literal ("-", ")") takes the character before it. */
export function maskedChange(mask: string, previous: string, typed: string): string {
  const next = applyMask(mask, typed);
  if (typed.length >= previous.length || next !== previous) return next;
  const shorter = applyMask(mask, typed.replace(/[\p{L}\p{N}](?=[^\p{L}\p{N}]*$)/u, ''));
  return shorter === previous ? '' : shorter;
}
