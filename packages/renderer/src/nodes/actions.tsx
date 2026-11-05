import { Link } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { Button as AriaButton, Dialog, Heading, Modal, ModalOverlay } from 'react-aria-components';
import { t } from '../i18n';
import { isInternal, nodeAttributes, useSnapshot, useStore } from '../store';
import { buttonClass, cx, formatHotkey, Icon, Kbd, matchesHotkey, Spinner } from '../ui';
import type { ActionRef, UiNode } from '../types';

export type ButtonNode = UiNode & {
  label: string;
  variant?: string;
  icon?: string;
  hotkey?: string;
  block?: boolean;
  confirm?: { title: string; text?: string; confirm?: string; danger?: boolean };
  action?: ActionRef;
};

/** Asks before running a dangerous action. */
export function ConfirmDialog({
  open,
  onOpenChange,
  confirm,
  danger,
  icon,
  label,
  onConfirm,
}: {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  confirm: NonNullable<ButtonNode['confirm']>;
  danger: boolean;
  icon?: string;
  label: string;
  onConfirm: () => void;
}) {
  return (
    <ModalOverlay isOpen={open} onOpenChange={onOpenChange} isDismissable className="fixed inset-0 z-50 grid place-items-center bg-(--v-scrim) p-4">
      <Modal className="w-full max-w-sm rounded-2xl border border-line bg-surface p-6 shadow-pop outline-none">
        <Dialog role="alertdialog" className="flex flex-col gap-4 outline-none">
          {({ close }) => (
            <>
              <span aria-hidden="true" className={cx('grid size-10 place-items-center rounded-full', danger ? 'bg-danger-soft text-danger-fg' : 'bg-accent-soft text-accent-fg')}>
                <Icon name={icon ?? 'alert'} className="size-5" />
              </span>
              <div className="flex flex-col gap-1.5">
                <Heading slot="title" className="text-[16px] font-semibold">
                  {confirm.title}
                </Heading>
                {confirm.text && <p className="text-[13.5px] text-fg-2">{confirm.text}</p>}
              </div>
              <div className="flex justify-end gap-2">
                <AriaButton onPress={close} className={buttonClass('secondary')}>
                  {t('cancel')}
                </AriaButton>
                <AriaButton
                  autoFocus
                  onPress={() => {
                    close();
                    onConfirm();
                  }}
                  className={buttonClass(danger ? 'danger' : 'primary')}
                >
                  {confirm.confirm ?? label}
                </AriaButton>
              </div>
            </>
          )}
        </Dialog>
      </Modal>
    </ModalOverlay>
  );
}

export function ButtonView({ node, size = 'md', ids }: { node: ButtonNode; size?: 'sm' | 'md'; ids?: string[] }) {
  const store = useStore();
  const running = useSnapshot((s) => s.running !== null && s.running === node.id);
  const busy = useSnapshot((s) => s.running !== null);
  const [confirming, setConfirming] = useState(false);
  const danger = node.variant === 'danger' || Boolean(node.confirm?.danger);

  const fire = () => {
    if (node.action) void store.runAction(node.id, node.action, ids);
  };
  const run = () => {
    if (!node.action || busy) return;
    if (node.confirm) setConfirming(true);
    else fire();
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
        className={cx(buttonClass(node.variant, size), node.block && 'w-full', node.block && size === 'md' && 'h-10')}
        {...nodeAttributes(node)}
      >
        {running ? <Spinner /> : <Icon name={node.icon} />}
        {node.label}
        {node.hotkey && <Kbd inverted={node.variant === 'primary'}>{formatHotkey(node.hotkey)}</Kbd>}
      </button>

      {node.confirm && (
        <ConfirmDialog open={confirming} onOpenChange={setConfirming} confirm={node.confirm} danger={danger} icon={node.icon} label={node.label} onConfirm={fire} />
      )}
    </>
  );
}

export function LinkView({ node, size = 'md' }: { node: UiNode & { label: string; href?: string; variant?: string; icon?: string; external?: boolean }; size?: 'sm' | 'md' }) {
  const className = node.variant === 'link' ? 'inline-flex items-center gap-1.5 font-medium text-accent-fg underline-offset-4 hover:underline' : buttonClass(node.variant, size);
  const content = (
    <>
      <Icon name={node.icon} />
      {node.label}
    </>
  );

  if (!node.external && isInternal(node.href)) {
    return (
      <Link href={node.href} prefetch className={className} {...nodeAttributes(node)}>
        {content}
      </Link>
    );
  }

  return (
    <a href={node.href} target={node.external ? '_blank' : undefined} rel={node.external ? 'noreferrer' : undefined} className={className} {...nodeAttributes(node)}>
      {content}
    </a>
  );
}

/** Opens a modal; with a load action, asks the server to fill it first. */
export function ModalToggleView({ node, compact }: { node: UiNode & { label: string; modal: string; variant?: string; icon?: string; action?: ActionRef }; compact?: boolean }) {
  const store = useStore();
  const running = useSnapshot((s) => s.running !== null && s.running === node.id);

  return (
    <button
      type="button"
      className={buttonClass(node.variant, compact ? 'sm' : 'md')}
      aria-haspopup="dialog"
      onClick={() => (node.action ? void store.runAction(node.id, node.action) : store.openModal(node.modal))}
      {...nodeAttributes(node)}
    >
      {running ? <Spinner /> : <Icon name={node.icon} />}
      {node.label}
    </button>
  );
}
