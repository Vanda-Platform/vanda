import { useEffect, useState } from 'react';
import { useSnapshot } from './store';
import { useCopy } from './copy';
import { t } from './i18n';
import { buttonClass, Icon } from './ui';

interface Picked {
  id: string;
  type: string;
  source?: string;
  x: number;
  y: number;
}

/**
 * Developer overlay: hold Alt and click any element to see which PHP line
 * created it. "Copy for AI" puts the context an agent needs on the clipboard.
 * Only active when the server sent sources (vanda.dev / local environment).
 */
export function Inspector() {
  const screen = useSnapshot((s) => s.page.screen);
  const url = useSnapshot((s) => s.page.url);
  const [picked, setPicked] = useState<Picked | null>(null);
  const { copied, copy } = useCopy();

  useEffect(() => {
    const onKey = (event: KeyboardEvent) => {
      document.documentElement.toggleAttribute('data-vanda-inspect', event.altKey && document.querySelector('[data-vanda-source]') !== null);
    };
    const onClick = (event: MouseEvent) => {
      if (!event.altKey) return;
      const element = (event.target as HTMLElement).closest<HTMLElement>('[data-vanda-node]');
      if (!element?.dataset.vandaSource) return;
      event.preventDefault();
      event.stopPropagation();
      setPicked({
        id: element.dataset.vandaNode ?? '',
        type: element.dataset.vandaType ?? '',
        source: element.dataset.vandaSource,
        x: Math.min(event.clientX, window.innerWidth - 380),
        y: Math.min(event.clientY + 12, window.innerHeight - 200),
      });
    };

    window.addEventListener('keydown', onKey);
    window.addEventListener('keyup', onKey);
    window.addEventListener('click', onClick, true);
    return () => {
      window.removeEventListener('keydown', onKey);
      window.removeEventListener('keyup', onKey);
      window.removeEventListener('click', onClick, true);
    };
  }, []);

  if (!picked) return null;

  const context = JSON.stringify({ screen, url, node: picked.id, type: picked.type, source: picked.source }, null, 2);

  return (
    <div
      role="dialog"
      aria-label={t('inspector')}
      className="fixed z-[60] flex w-[360px] flex-col gap-2.5 rounded-xl border border-line bg-surface p-3.5 text-[13px] shadow-pop"
      style={{ left: picked.x, top: picked.y }}
    >
      <div className="flex items-center justify-between">
        <span className="font-semibold">{t('inspector')}</span>
        <button type="button" onClick={() => setPicked(null)} className="text-fg-3 hover:text-fg" aria-label={t('close')}>
          ✕
        </button>
      </div>
      <dl className="grid grid-cols-[64px_minmax(0,1fr)] gap-x-3 gap-y-1">
        <dt className="text-fg-3">id</dt>
        <dd className="truncate font-mono">{picked.id}</dd>
        <dt className="text-fg-3">type</dt>
        <dd className="font-mono">{picked.type}</dd>
        <dt className="text-fg-3">source</dt>
        <dd className="font-mono break-all">{picked.source}</dd>
      </dl>
      <button
        type="button"
        className={buttonClass('secondary', 'sm')}
        onClick={() => void copy(context)}
      >
        <Icon name={copied ? 'check' : 'copy'} />
        {copied ? t('copied') : t('copyForAi')}
      </button>
    </div>
  );
}
