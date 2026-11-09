/** The relative luminance of an sRGB colour given as #rgb or #rrggbb (WCAG 2), null for anything else. */
function luminance(hex: string): number | null {
  const match = /^#?([0-9a-f]{3}|[0-9a-f]{6})$/i.exec(hex.trim());
  if (!match) return null;
  const digits = match[1]!.length === 3 ? [...match[1]!].map((digit) => digit + digit).join('') : match[1]!;
  const [r, g, b] = [0, 2, 4].map((at) => {
    const channel = parseInt(digits.slice(at, at + 2), 16) / 255;
    return channel <= 0.03928 ? channel / 12.92 : ((channel + 0.055) / 1.055) ** 2.4;
  });
  return 0.2126 * r! + 0.7152 * g! + 0.0722 * b!;
}

const contrast = (a: number, b: number) => (Math.max(a, b) + 0.05) / (Math.min(a, b) + 0.05);

/** The ink of text on the brand colour. */
export const DARK_INK = '#18181b';

/**
 * The text colour on the brand colour (primary buttons, the checked checkbox, badges): white, or
 * the dark ink when a light brand (a yellow, a light green) would leave white text unreadable.
 * A colour other than #rgb / #rrggbb keeps white.
 *
 *     onAccent('#12805C') // '#ffffff'
 *     onAccent('#FFD60A') // '#18181b'
 */
export function onAccent(brand: string): string {
  const background = luminance(brand);
  if (background === null) return '#ffffff';
  return contrast(background, 1) >= contrast(background, luminance(DARK_INK)!) ? '#ffffff' : DARK_INK;
}
