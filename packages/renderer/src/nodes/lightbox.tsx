import { useEffect, useState } from 'react';
import { Dialog, Heading, Modal, ModalOverlay } from 'react-aria-components';
import { t } from '../i18n';
import { cx, Icon } from '../ui';
import { formatSize } from './upload';

export interface ViewedImage {
  url: string;
  name: string;
  size?: number | null;
}

/** The address to share: relative URLs from the server become absolute. */
const absolute = (url: string) => new URL(url, window.location.href).href;

async function copy(text: string): Promise<boolean> {
  try {
    await navigator.clipboard.writeText(text);
    return true;
  } catch {
    // No clipboard API outside https and localhost: the old way still works there.
    const area = document.createElement('textarea');
    area.value = text;
    area.style.position = 'fixed';
    area.style.opacity = '0';
    document.body.append(area);
    area.select();
    const done = document.execCommand('copy');
    area.remove();
    return done;
  }
}

const tool = 'inline-flex h-8 items-center gap-1.5 rounded-lg px-2.5 text-[13px] font-medium text-white/85 hover:bg-white/12 hover:text-white focus-visible:outline-2 focus-visible:outline-white';
const arrow = 'absolute top-1/2 grid size-10 -translate-y-1/2 place-items-center rounded-full bg-black/45 text-white hover:bg-black/65 focus-visible:outline-2 focus-visible:outline-white';

/**
 * Pictures of an Upload field shown over the page: copy the link, download, open in a tab;
 * ← and → move between the pictures of the field.
 */
export function Lightbox({ images, start, onClose }: { images: ViewedImage[]; start: number; onClose: () => void }) {
  const [index, setIndex] = useState(start);
  const [copied, setCopied] = useState(false);
  const [natural, setNatural] = useState<{ width: number; height: number } | null>(null);
  const image = images[index] ?? images[0];
  const many = images.length > 1;

  useEffect(() => {
    setNatural(null);
    setCopied(false);
  }, [index]);

  useEffect(() => {
    if (!many) return;
    const onKey = (event: KeyboardEvent) => {
      if (event.key === 'ArrowLeft') setIndex((current) => (current - 1 + images.length) % images.length);
      if (event.key === 'ArrowRight') setIndex((current) => (current + 1) % images.length);
    };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [many, images.length]);

  if (!image) return null;

  const details = [natural && `${natural.width}×${natural.height}`, image.size != null && formatSize(image.size), many && `${index + 1} / ${images.length}`].filter(Boolean).join(' · ');

  return (
    <ModalOverlay isOpen isDismissable onOpenChange={(open) => !open && onClose()} className="fixed inset-0 z-50 bg-[oklch(0.14_0_0/0.96)] backdrop-blur-sm">
      <Modal className="h-full w-full outline-none">
        <Dialog aria-label={image.name} className="flex h-full flex-col outline-none">
          <div className="flex flex-wrap items-center gap-2 px-4 py-3 text-white">
            <div className="flex min-w-0 flex-1 flex-col">
              <Heading slot="title" className="truncate text-[14px] font-semibold">
                {image.name}
              </Heading>
              {details && <span className="text-[12.5px] text-white/60 tabular-nums">{details}</span>}
            </div>
            <button type="button" className={tool} onClick={() => void copy(absolute(image.url)).then((done) => setCopied(done))}>
              <Icon name={copied ? 'check' : 'copy'} />
              {copied ? t('linkCopied') : t('copyLink')}
            </button>
            <a className={tool} href={image.url} download={image.name}>
              <Icon name="download" />
              {t('download')}
            </a>
            <a className={tool} href={image.url} target="_blank" rel="noreferrer" aria-label={t('openInTab')} title={t('openInTab')}>
              <Icon name="external" />
            </a>
            <button type="button" className={tool} onClick={onClose} aria-label={t('close')}>
              <Icon name="x" />
            </button>
          </div>

          {/* A click beside the picture closes the viewer, as on the dark backdrop. */}
          <div className="relative flex min-h-0 flex-1 items-center justify-center px-4 pb-6" onClick={(event) => event.target === event.currentTarget && onClose()}>
            <img
              key={image.url}
              src={image.url}
              alt={image.name}
              onLoad={(event) => setNatural({ width: event.currentTarget.naturalWidth, height: event.currentTarget.naturalHeight })}
              className={cx('max-h-full max-w-full rounded-md object-contain shadow-pop', !natural && 'opacity-0')}
            />
            {many && (
              <>
                <button type="button" className={cx(arrow, 'left-4')} aria-label={t('previousImage')} onClick={() => setIndex((index - 1 + images.length) % images.length)}>
                  <Icon name="chevron-left" />
                </button>
                <button type="button" className={cx(arrow, 'right-4')} aria-label={t('nextImage')} onClick={() => setIndex((index + 1) % images.length)}>
                  <Icon name="chevron-right" />
                </button>
              </>
            )}
          </div>
        </Dialog>
      </Modal>
    </ModalOverlay>
  );
}
