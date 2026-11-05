import { useEffect, useState } from 'react';
import { Button as AriaButton, Dialog, Heading, Modal, ModalOverlay } from 'react-aria-components';
import { nodeAttributes, useSnapshot, useStore } from '../store';
import { t } from '../i18n';
import { buttonClass, cx, formatHotkey, Icon, Kbd, matchesHotkey, Spinner } from '../ui';
import type { ActionRef, UiNode } from '../types';

type ButtonNode = UiNode & {
  label: string;
  variant?: string;
  icon?: string;
  hotkey?: string;
  confirm?: { title: string; text?: string; confirm?: string; danger?: boolean };
  action?: ActionRef;
};

export function ButtonView({ node, size = 'md' }: { node: ButtonNode; size?: 'sm' | 'md' }) {
  const store = useStore();
  const running = useSnapshot((s) => s.running !== null && s.running === node.id);
  const busy = useSnapshot((s) => s.running !== null);
  const [confirming, setConfirming] = useState(false);
  const danger = node.variant === 'danger' || Boolean(node.confirm?.danger);

  const run = () => {
    if (!node.action || busy) return;
    if (node.confirm) setConfirming(true);
    else void store.runAction(node.id, node.action);
  };

  useEffect(() => {
    if (!node.hotkey) return;
    const onKey = (event: KeyboardEvent) => {
      if (matchesHotkey(event, node.hotkey!)) {
        event.preventDefault();
        run();
      }
    };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  });

  return (
    <>
      <button
        type="button"
        onClick={run}
        disabled={busy && !running}
        aria-busy={running || undefined}
        className={buttonClass(node.variant, size)}
        {...nodeAttributes(node)}
      >
        {running ? <Spinner /> : <Icon name={node.icon} />}
        {node.label}
        {node.hotkey && <Kbd inverted={node.variant === 'primary'}>{formatHotkey(node.hotkey)}</Kbd>}
      </button>

      {node.confirm && (
        <ModalOverlay
          isOpen={confirming}
          onOpenChange={setConfirming}
          isDismissable
          className="fixed inset-0 z-50 grid place-items-center bg-(--v-scrim) p-4"
        >
          <Modal className="w-full max-w-sm rounded-2xl border border-line bg-surface p-6 shadow-pop outline-none">
            <Dialog role="alertdialog" className="flex flex-col gap-4 outline-none">
              {({ close }) => (
                <>
                  <span
                    aria-hidden="true"
                    className={cx(
                      'grid size-10 place-items-center rounded-full',
                      danger ? 'bg-danger-soft text-danger-fg' : 'bg-accent-soft text-accent-fg',
                    )}
                  >
                    <Icon name={node.icon ?? 'alert'} className="size-5" />
                  </span>
                  <div className="flex flex-col gap-1.5">
                    <Heading slot="title" className="text-[16px] font-semibold">
                      {node.confirm!.title}
                    </Heading>
                    {node.confirm!.text && <p className="text-[13.5px] text-fg-2">{node.confirm!.text}</p>}
                  </div>
                  <div className="flex justify-end gap-2">
                    <AriaButton onPress={close} className={buttonClass('secondary')}>
                      {t('cancel')}
                    </AriaButton>
                    <AriaButton
                      autoFocus
                      onPress={() => {
                        close();
                        void store.runAction(node.id, node.action!);
                      }}
                      className={buttonClass(danger ? 'danger' : 'primary')}
                    >
                      {node.confirm!.confirm ?? node.label}
                    </AriaButton>
                  </div>
                </>
              )}
            </Dialog>
          </Modal>
        </ModalOverlay>
      )}
    </>
  );
}

export function LinkView({ node, size = 'md' }: { node: UiNode & { label: string; href?: string; variant?: string; icon?: string; external?: boolean }; size?: 'sm' | 'md' }) {
  return (
    <a
      href={node.href}
      target={node.external ? '_blank' : undefined}
      rel={node.external ? 'noreferrer' : undefined}
      className={node.variant === 'link' ? 'font-medium text-accent-fg underline-offset-4 hover:underline' : buttonClass(node.variant, size)}
      {...nodeAttributes(node)}
    >
      <Icon name={node.icon} />
      {node.label}
    </a>
  );
}
