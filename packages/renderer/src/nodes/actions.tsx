import { Link, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { Button as AriaButton, Dialog, Heading, Menu, MenuItem, MenuTrigger, Modal, ModalOverlay, Popover, Separator } from 'react-aria-components';
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

type MenuEntry = ButtonNode & { href?: string; external?: boolean; modal?: string };

/** "⋯" and other menus of buttons, links and modal toggles. */
export function DropDownView({ node, compact }: { node: UiNode & { label?: string; icon?: string; variant?: string }; compact?: boolean }) {
  const store = useStore();
  const [confirming, setConfirming] = useState<MenuEntry | null>(null);
  const items = (node.children ?? []) as MenuEntry[];
  const iconOnly = !node.label;

  const activate = (item: MenuEntry) => {
    if (item.type === 'link' && item.href) {
      if (!item.external && isInternal(item.href)) router.visit(item.href);
      else window.open(item.href, item.external ? '_blank' : '_self');
    } else if (item.type === 'modaltoggle') {
      if (item.action) void store.runAction(item.id, item.action);
      else if (item.modal) store.openModal(item.modal);
    } else if (item.action) {
      if (item.confirm) setConfirming(item);
      else void store.runAction(item.id, item.action);
    }
  };

  return (
    <>
      <MenuTrigger>
        <AriaButton
          aria-label={iconOnly ? t('actions') : undefined}
          className={cx(buttonClass(node.variant, compact ? 'sm' : 'md'), iconOnly && (compact ? 'w-7 px-0' : 'w-9 px-0'))}
          {...nodeAttributes(node)}
        >
          <Icon name={node.icon} />
          {node.label}
          {!iconOnly && <Icon name="chevron-down" className="size-3.5 text-fg-3" />}
        </AriaButton>
        <Popover placement="bottom end" className="min-w-52 rounded-xl border border-line bg-surface p-1 shadow-pop outline-none">
          <Menu className="outline-none" onAction={(key) => {
            const item = items.find((entry) => entry.id === key);
            if (item) activate(item);
          }}>
            {items.map((item, index) => {
              const danger = item.variant === 'danger';
              return [
                danger && index > 0 ? <Separator key={`${item.id}-separator`} className="my-1 border-t border-line" /> : null,
                <MenuItem
                  key={item.id}
                  id={item.id}
                  textValue={item.label}
                  className={cx(
                    'flex h-8 cursor-pointer items-center gap-2.5 rounded-lg px-2.5 text-[13.5px] outline-none',
                    danger ? 'text-danger-fg data-[focused]:bg-danger-soft' : 'text-fg data-[focused]:bg-surface-2',
                  )}
                >
                  <Icon name={item.icon} className={cx('size-4', !danger && 'text-fg-3')} />
                  <span className="flex-1">{item.label}</span>
                  {item.hotkey && <Kbd>{formatHotkey(item.hotkey)}</Kbd>}
                </MenuItem>,
              ];
            })}
          </Menu>
        </Popover>
      </MenuTrigger>

      {confirming?.confirm && (
        <ConfirmDialog
          open
          onOpenChange={(open) => !open && setConfirming(null)}
          confirm={confirming.confirm}
          danger={confirming.variant === 'danger' || Boolean(confirming.confirm.danger)}
          icon={confirming.icon}
          label={confirming.label}
          onConfirm={() => confirming.action && void store.runAction(confirming.id, confirming.action)}
        />
      )}
    </>
  );
}
