import { describe, expect, it, vi } from 'vitest';
import { convertible, toWebp, webpName } from './webp';

describe('WebP before upload', () => {
  it('takes only pictures a canvas redraws without loss of anything but bytes', () => {
    expect(['image/jpeg', 'image/png', 'image/bmp'].every(convertible)).toBe(true);
    expect(['image/gif', 'image/svg+xml', 'image/webp', 'application/pdf', undefined].some(convertible)).toBe(false);
  });

  it('names the result after the original', () => {
    expect(webpName('photo.final.JPG')).toBe('photo.final.webp');
    expect(webpName('scan')).toBe('scan.webp');
    expect(webpName('.png')).toBe('image.webp');
  });

  it('sends the file as it was where it cannot convert', async () => {
    const gif = new File(['GIF89a'], 'cat.gif', { type: 'image/gif' });
    expect(await toWebp(gif, 85)).toBe(gif);

    // No canvas here (as in a browser that cannot write WebP): the JPEG goes as it is.
    vi.stubGlobal('document', { createElement: () => ({ toDataURL: () => 'data:image/png;base64,' }) });
    const jpeg = new File(['jpeg'], 'photo.jpg', { type: 'image/jpeg' });
    expect(await toWebp(jpeg, 85)).toBe(jpeg);
    vi.unstubAllGlobals();
  });
});
