import Cropper from 'cropperjs';
import 'cropperjs/dist/cropper.css';
import { useEffect, useRef, useState } from 'react';
import { Dialog, Heading, Modal, ModalOverlay } from 'react-aria-components';
import { t } from '../i18n';
import { buttonClass, cx, Icon } from '../ui';

/** One of Upload::crop() from the code (Vanda\Fields\Crop). */
export interface CropPreset {
  mode: 'size' | 'ratio' | 'free';
  width?: number;
  height?: number;
  label?: string;
  maxWidth?: number;
  minWidth?: number;
  minHeight?: number;
}

interface Props {
  /** An object URL of a chosen file or the URL of an image already in the field. */
  src: string;
  name: string;
  mime: string;
  presets: CropPreset[];
  /** Upload::customCrop(): free crop, the user's own sizes and "upload as it is". */
  custom: boolean;
  /** Where the user's own sizes are remembered (per field). */
  storageKey: string;
  /** The cropped file, or the original when the user chose not to crop. */
  onDone: (file: File | 'original') => void;
  onCancel: () => void;
}

type Size = { width: number; height: number };

const OUTPUT_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

function loadSizes(key: string): Size[] {
  try {
    const parsed: unknown = JSON.parse(localStorage.getItem(key) ?? '[]');
    return Array.isArray(parsed) ? parsed.filter((size): size is Size => typeof size?.width === 'number' && typeof size?.height === 'number').slice(0, 4) : [];
  } catch {
    return [];
  }
}

function saveSizes(key: string, sizes: Size[]) {
  try {
    localStorage.setItem(key, JSON.stringify(sizes.slice(0, 4)));
  } catch {
    // Private mode or blocked storage: the sizes are simply not remembered.
  }
}

const sizeLabel = ({ width, height }: Size) => `${width}×${height}`;

/**
 * Crops an image in the browser before it is uploaded: presets from the code, and with
 * customCrop() also a free frame and sizes the user types (kept in their browser).
 */
export function CropDialog({ src, name, mime, presets, custom, storageKey, onDone, onCancel }: Props) {
  const image = useRef<HTMLImageElement>(null);
  const cropper = useRef<Cropper | null>(null);
  const [saved, setSaved] = useState<Size[]>(() => loadSizes(storageKey));
  const choices: CropPreset[] = [
    ...presets,
    ...(custom ? saved.map<CropPreset>((size) => ({ mode: 'size', ...size })) : []),
    ...(custom && !presets.some((preset) => preset.mode === 'free') ? [{ mode: 'free' } as CropPreset] : []),
  ];
  const [active, setActive] = useState(0);
  const [typing, setTyping] = useState<Size | null>(null);
  const [area, setArea] = useState<Size>({ width: 0, height: 0 });
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  // A size being typed is a draft: the frame changes only when it is kept, not on every digit.
  const preset = choices[active] ?? { mode: 'free' };
  const ratio = preset.mode !== 'free' && preset.width && preset.height ? preset.width / preset.height : NaN;

  useEffect(() => {
    if (!image.current) return;
    const instance = new Cropper(image.current, {
      viewMode: 1,
      dragMode: 'move',
      aspectRatio: ratio,
      autoCropArea: 0.9,
      background: false,
      checkCrossOrigin: true,
      toggleDragModeOnDblclick: false,
      crop: (event) => setArea({ width: Math.round(event.detail.width), height: Math.round(event.detail.height) }),
    });
    cropper.current = instance;
    return () => {
      instance.destroy();
      cropper.current = null;
    };
    // The frame's proportions change through setAspectRatio below, not by rebuilding.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [src]);

  useEffect(() => {
    const instance = cropper.current;
    if (!instance) return;
    instance.setAspectRatio(ratio);
    // The crop event fired by setAspectRatio() still carries the old frame, so read it here.
    const data = instance.getData(true);
    if (data.width > 0) setArea({ width: data.width, height: data.height });
  }, [ratio]);

  const output: Size = (() => {
    if (preset.mode === 'size' && preset.width && preset.height) return { width: preset.width, height: preset.height };
    const scale = preset.maxWidth && area.width > preset.maxWidth ? preset.maxWidth / area.width : 1;
    return { width: Math.round(area.width * scale), height: Math.round(area.height * scale) };
  })();
  const tooSmall = Boolean((preset.minWidth && area.width < preset.minWidth) || (preset.minHeight && area.height < preset.minHeight));
  const upscaled = !tooSmall && area.width > 0 && (area.width < output.width || area.height < output.height);

  const apply = () => {
    const instance = cropper.current;
    if (!instance || output.width < 1 || output.height < 1 || area.width < 1 || area.height < 1) return;
    const type = OUTPUT_TYPES.includes(mime) ? mime : 'image/png';
    setBusy(true);
    setError(null);
    try {
      const canvas = instance.getCroppedCanvas({
        width: output.width,
        height: output.height,
        imageSmoothingEnabled: true,
        imageSmoothingQuality: 'high',
        fillColor: type === 'image/jpeg' ? '#fff' : 'transparent',
      });
      canvas.toBlob(
        (blob) => {
          setBusy(false);
          if (!blob) return setError(t('cropFailed'));
          const base = name.replace(/\.[^.]+$/, '') || 'image';
          onDone(new File([blob], `${base}.${type.split('/')[1]!.replace('jpeg', 'jpg')}`, { type }));
        },
        type,
        0.9,
      );
    } catch {
      // A picture from another site without CORS headers cannot be read back from a canvas.
      setBusy(false);
      setError(t('cropFailed'));
    }
  };

  const remember = (size: Size) => {
    const next = [size, ...saved.filter((item) => sizeLabel(item) !== sizeLabel(size))].slice(0, 4);
    setSaved(next);
    saveSizes(storageKey, next);
    setTyping(null);
    setActive(presets.length);
  };

  const forget = (size: Size) => {
    const next = saved.filter((item) => sizeLabel(item) !== sizeLabel(size));
    setSaved(next);
    saveSizes(storageKey, next);
    setActive(0);
  };

  const chip = (selected: boolean) =>
    cx(
      'inline-flex h-8 items-center gap-1.5 rounded-lg border px-3 text-[13px] whitespace-nowrap transition-colors',
      selected ? 'border-accent bg-accent-soft font-medium text-accent-fg' : 'border-line text-fg-2 hover:border-line-strong hover:text-fg',
    );
  const tool = 'grid size-8 place-items-center rounded-lg text-fg-2 hover:bg-surface-2 hover:text-fg';

  return (
    <ModalOverlay isOpen isDismissable={!busy} onOpenChange={(open) => !open && onCancel()} className="fixed inset-0 z-50 grid place-items-center overflow-y-auto bg-(--v-scrim) p-4">
      <Modal className="w-full max-w-3xl rounded-xl border border-line bg-surface shadow-pop outline-none">
        <Dialog className="flex flex-col outline-none" aria-label={t('cropTitle')}>
          <div className="flex items-start justify-between gap-3 px-5 pt-[18px] pb-3">
            <div className="flex min-w-0 flex-col gap-0.5">
              <Heading slot="title" className="text-[16px] font-semibold">
                {t('cropTitle')}
              </Heading>
              <p className="truncate text-[13px] text-fg-3">{name}</p>
            </div>
            <button type="button" onClick={onCancel} aria-label={t('close')} className="grid size-7 place-items-center rounded-md text-fg-3 hover:bg-surface-2 hover:text-fg">
              <Icon name="x" />
            </button>
          </div>

          {(choices.length > 1 || custom) && (
            <div role="radiogroup" aria-label={t('cropShape')} className="flex flex-wrap items-center gap-1.5 px-5 pb-3">
              {choices.map((choice, index) => {
                const own = index >= presets.length && choice.mode === 'size';
                const dims = choice.mode !== 'free' && choice.width && choice.height ? (choice.mode === 'ratio' ? `${choice.width}:${choice.height}` : sizeLabel(choice as Size)) : null;
                return (
                  <span key={index} className="inline-flex items-center">
                    <button
                      type="button"
                      role="radio"
                      aria-checked={active === index}
                      onClick={() => {
                        setTyping(null);
                        setActive(index);
                      }}
                      className={chip(active === index)}
                    >
                      {choice.mode === 'free' ? t('cropFree') : (choice.label ?? dims)}
                      {choice.label && dims && <span className="text-fg-3 tabular-nums">{dims}</span>}
                    </button>
                    {own && (
                      <button type="button" aria-label={t('cropForget', { size: dims ?? '' })} onClick={() => forget(choice as Size)} className="-ml-1 grid size-6 place-items-center rounded-md text-fg-3 hover:text-fg">
                        <Icon name="x" className="size-3" />
                      </button>
                    )}
                  </span>
                );
              })}
              {custom &&
                (typing ? (
                  <form
                    className="inline-flex items-center gap-1"
                    onKeyDown={(event) => event.key === 'Escape' && (event.stopPropagation(), setTyping(null))}
                    onSubmit={(event) => {
                      event.preventDefault();
                      if (typing.width > 0 && typing.height > 0) remember(typing);
                    }}
                  >
                    {(['width', 'height'] as const).map((side, index) => (
                      <span key={side} className="inline-flex items-center gap-1">
                        {index === 1 && <span className="text-fg-3">×</span>}
                        <input
                          type="number"
                          min={1}
                          max={10000}
                          autoFocus={index === 0}
                          aria-label={t(side === 'width' ? 'cropWidth' : 'cropHeight')}
                          value={typing[side] || ''}
                          onChange={(event) => setTyping({ ...typing, [side]: Math.min(10000, Math.max(0, Math.round(Number(event.target.value) || 0))) })}
                          className="h-8 w-[76px] rounded-lg border border-line bg-surface px-2 text-[13px] tabular-nums outline-none focus:border-accent"
                        />
                      </span>
                    ))}
                    <button type="submit" disabled={!(typing.width > 0 && typing.height > 0)} className={buttonClass('secondary', 'sm')}>
                      {t('cropKeep')}
                    </button>
                  </form>
                ) : (
                  <button type="button" onClick={() => setTyping({ width: output.width, height: output.height })} className={chip(false)}>
                    <Icon name="plus" className="size-3.5" />
                    {t('cropCustom')}
                  </button>
                ))}
            </div>
          )}

          <div className="h-[min(60vh,460px)] bg-[oklch(0.2_0_0)]">
            <img ref={image} src={src} alt="" className="block max-w-full" onError={() => setError(t('cropFailed'))} />
          </div>

          <div className="flex flex-wrap items-center gap-1 border-b border-line px-3 py-2">
            <button type="button" className={tool} aria-label={t('zoomOut')} title={t('zoomOut')} onClick={() => cropper.current?.zoom(-0.1)}>
              <Icon name="zoom-out" />
            </button>
            <button type="button" className={tool} aria-label={t('zoomIn')} title={t('zoomIn')} onClick={() => cropper.current?.zoom(0.1)}>
              <Icon name="zoom-in" />
            </button>
            <button type="button" className={tool} aria-label={t('rotateLeft')} title={t('rotateLeft')} onClick={() => cropper.current?.rotate(-90)}>
              <Icon name="rotate-left" />
            </button>
            <button type="button" className={tool} aria-label={t('rotateRight')} title={t('rotateRight')} onClick={() => cropper.current?.rotate(90)}>
              <Icon name="rotate" />
            </button>
            <button type="button" className={tool} aria-label={t('cropReset')} title={t('cropReset')} onClick={() => cropper.current?.reset()}>
              <Icon name="undo" />
            </button>
            <span className="ml-auto px-2 text-[12.5px] text-fg-3 tabular-nums" aria-live="polite">
              {tooSmall ? (
                <span className="text-danger-fg">{t('cropTooSmall', { size: sizeLabel({ width: preset.minWidth ?? 0, height: preset.minHeight ?? 0 }) })}</span>
              ) : upscaled ? (
                <span className="text-warning-fg">{t('cropUpscale', { size: sizeLabel(area), result: sizeLabel(output) })}</span>
              ) : (
                t('cropResult', { size: sizeLabel(output) })
              )}
            </span>
          </div>

          {error && <p className="px-5 pt-3 text-[13px] text-danger-fg">{error}</p>}

          <div className="flex flex-wrap items-center gap-2 px-5 pt-3 pb-4">
            {custom && (
              <button type="button" className={buttonClass('ghost')} onClick={() => onDone('original')} disabled={busy}>
                {t('cropOriginal')}
              </button>
            )}
            <span className="flex-1" />
            <button type="button" className={buttonClass('secondary')} onClick={onCancel} disabled={busy}>
              {t('cancel')}
            </button>
            <button type="button" className={buttonClass('primary')} onClick={apply} disabled={busy || tooSmall || area.width < 1 || area.height < 1}>
              <Icon name="crop" />
              {t('cropApply')}
            </button>
          </div>
        </Dialog>
      </Modal>
    </ModalOverlay>
  );
}
