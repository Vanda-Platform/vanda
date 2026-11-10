/**
 * Pictures re-encoded as WebP in the browser before they are uploaded (Upload::webp(), on by default
 * for picture fields): a phone's JPEG gets several times lighter, the eye hardly sees a difference
 * at quality 85, and the camera's metadata (GPS included) stays on the phone. A picture that would
 * not get lighter is sent as it was, and so is everything when the browser cannot write WebP.
 */

/** What a canvas redraws without losing more than bytes: not GIF (animation), not SVG (vector), not WebP already. */
const CONVERTIBLE = new Set(['image/jpeg', 'image/png', 'image/bmp']);

export const convertible = (type: string | undefined) => CONVERTIBLE.has(type ?? '');

/** "photo.final.JPG" → "photo.final.webp". */
export const webpName = (name: string) => `${name.replace(/\.[^./\\]+$/, '') || 'image'}.webp`;

let supported: boolean | undefined;

/** Whether this browser writes WebP from a canvas (one that cannot writes PNG instead). */
export function canWriteWebp(): boolean {
  if (supported === undefined) {
    try {
      const canvas = document.createElement('canvas');
      canvas.width = canvas.height = 1;
      supported = canvas.toDataURL('image/webp').startsWith('data:image/webp');
    } catch {
      supported = false;
    }
  }
  return supported;
}

/** A canvas as a file of `type`, or null when the browser cannot write it (too big, another type came out). */
export function canvasToFile(canvas: HTMLCanvasElement, type: string, quality: number, name: string): Promise<File | null> {
  return new Promise((resolve) => {
    canvas.toBlob(
      (blob) => resolve(blob && blob.type === type ? new File([blob], name, { type }) : null),
      type,
      quality,
    );
  });
}

/**
 * The picture as WebP at `quality` (1–100), or the file itself when it is not a picture to convert,
 * the browser cannot do it, or WebP would not be lighter.
 */
export async function toWebp(file: File, quality: number): Promise<File> {
  if (!convertible(file.type) || !canWriteWebp() || typeof createImageBitmap !== 'function') return file;
  try {
    // Turned as the camera meant it (EXIF orientation), as an <img> shows it.
    const bitmap = await createImageBitmap(file, { imageOrientation: 'from-image' });
    const canvas = document.createElement('canvas');
    canvas.width = bitmap.width;
    canvas.height = bitmap.height;
    const context = canvas.getContext('2d');
    if (!context) return file;
    context.drawImage(bitmap, 0, 0);
    bitmap.close();
    const converted = await canvasToFile(canvas, 'image/webp', quality / 100, webpName(file.name));
    // Free the canvas memory at once: a 48-megapixel photo holds about 200 MB.
    canvas.width = canvas.height = 0;
    return converted && converted.size < file.size ? converted : file;
  } catch {
    return file;
  }
}
