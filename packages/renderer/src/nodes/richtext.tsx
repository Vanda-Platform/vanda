import { EditorContent, useEditor, useEditorState, type Editor } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import { useEffect, useId, useRef, useState, type ReactNode } from 'react';
import { Button as AriaButton, Dialog, DialogTrigger, Popover } from 'react-aria-components';
import { t } from '../i18n';
import { buttonClass, controlClass, cx, Icon, invalidClass } from '../ui';
import type { FieldNode } from '../types';
import { FieldFrame, useField } from './fields';

// Tiptap lives in its own chunk, loaded only by screens with a RichText field.

function ToolButton({ label, active, disabled, onPress, children }: { label: string; active?: boolean; disabled?: boolean; onPress: () => void; children: ReactNode }) {
  return (
    <button
      type="button"
      aria-label={label}
      title={label}
      aria-pressed={active}
      disabled={disabled}
      onMouseDown={(event) => event.preventDefault()}
      onClick={onPress}
      className={cx('grid size-7 place-items-center rounded-md text-fg-2 hover:bg-surface-2 hover:text-fg disabled:opacity-40', active && 'bg-accent-soft text-accent-fg hover:bg-accent-soft hover:text-accent-fg')}
    >
      {children}
    </button>
  );
}

const Divider = () => <span aria-hidden="true" className="mx-1 h-5 w-px bg-line" />;

function LinkButton({ editor }: { editor: Editor }) {
  const [href, setHref] = useState('');
  const active = editor.isActive('link');

  return (
    <DialogTrigger onOpenChange={(open) => open && setHref(String(editor.getAttributes('link').href ?? ''))}>
      <AriaButton aria-label={t('link')} className={cx('grid size-7 place-items-center rounded-md text-fg-2 hover:bg-surface-2 hover:text-fg', active && 'bg-accent-soft text-accent-fg')}>
        <Icon name="link" />
      </AriaButton>
      <Popover placement="bottom start" className="w-80 rounded-xl border border-line bg-surface p-3 shadow-pop outline-none">
        <Dialog aria-label={t('link')} className="outline-none">
          {({ close }) => (
            <form
              className="flex flex-col gap-2"
              onSubmit={(event) => {
                event.preventDefault();
                const chain = editor.chain().focus().extendMarkRange('link');
                if (href.trim()) chain.setLink({ href: href.trim() }).run();
                else chain.unsetLink().run();
                close();
              }}
            >
              <label className="text-[13px] font-medium text-fg-2">
                {t('linkUrl')}
                <input autoFocus value={href} onChange={(event) => setHref(event.target.value)} placeholder="https://" className={cx(controlClass, 'mt-1.5')} />
              </label>
              <div className="flex justify-end gap-2">
                {active && (
                  <button
                    type="button"
                    className={buttonClass('ghost', 'sm')}
                    onClick={() => {
                      editor.chain().focus().extendMarkRange('link').unsetLink().run();
                      close();
                    }}
                  >
                    {t('remove')}
                  </button>
                )}
                <button type="submit" className={buttonClass('primary', 'sm')}>
                  {t('apply')}
                </button>
              </div>
            </form>
          )}
        </Dialog>
      </Popover>
    </DialogTrigger>
  );
}

export function RichTextField({ node }: { node: FieldNode & { minHeight?: number } }) {
  const id = useId();
  const field = useField(node);
  const value = typeof field.value === 'string' ? field.value : '';
  // The HTML this editor last produced or received; anything else in the state came from the server.
  const known = useRef(value);

  const editor = useEditor({
    extensions: [StarterKit.configure({ heading: { levels: [2, 3, 4] }, link: { openOnClick: false, autolink: true, defaultProtocol: 'https' } })],
    content: value,
    editable: !field.disabled,
    immediatelyRender: true,
    onUpdate: ({ editor: current }) => {
      known.current = current.isEmpty ? '' : current.getHTML();
      field.setValue(known.current);
    },
    editorProps: {
      attributes: { id, 'aria-label': node.label ?? node.path, class: 'vanda-prose px-4 py-3', style: `min-height: ${node.minHeight ?? 200}px` },
    },
  });

  const state = useEditorState({
    editor,
    selector: ({ editor: current }) => ({
      block: current.isActive('heading', { level: 2 }) ? 'h2' : current.isActive('heading', { level: 3 }) ? 'h3' : current.isActive('heading', { level: 4 }) ? 'h4' : 'p',
      bold: current.isActive('bold'),
      italic: current.isActive('italic'),
      underline: current.isActive('underline'),
      strike: current.isActive('strike'),
      code: current.isActive('code'),
      bulletList: current.isActive('bulletList'),
      orderedList: current.isActive('orderedList'),
      blockquote: current.isActive('blockquote'),
      empty: current.isEmpty,
      canUndo: current.can().undo(),
      canRedo: current.can().redo(),
    }),
  });

  // Text set by the server (a reaction or a refresh) replaces the content.
  // isDestroyed is also true before the editor is mounted; the initial content comes from the options.
  useEffect(() => {
    if (value === known.current || editor.isDestroyed) return;
    known.current = value;
    editor.commands.setContent(value, { emitUpdate: false });
  }, [editor, value]);

  useEffect(() => {
    if (!editor.isDestroyed) editor.setEditable(!field.disabled);
  }, [editor, field.disabled]);

  const run = (command: (chain: ReturnType<Editor['chain']>) => ReturnType<Editor['chain']>) => command(editor.chain().focus()).run();

  return (
    <FieldFrame node={node} inputId={id} required={field.required} error={field.error}>
      <div
        className={cx(
          'overflow-hidden rounded-lg border bg-surface shadow-sm transition focus-within:border-accent focus-within:ring-3 focus-within:ring-ring',
          field.error ? invalidClass : 'border-line-strong',
        )}
      >
        {!field.disabled && (
          <div role="toolbar" aria-label={node.label ?? node.path} className="flex flex-wrap items-center gap-0.5 border-b border-line bg-surface-2/60 px-1.5 py-1">
            <select
              aria-label={t('paragraph')}
              value={state.block}
              onChange={(event) => {
                const level = Number(event.target.value.slice(1));
                run((chain) => (event.target.value === 'p' ? chain.setParagraph() : chain.setHeading({ level: level as 2 | 3 | 4 })));
              }}
              className="h-7 rounded-md bg-transparent px-1.5 text-[13px] font-medium text-fg-2 hover:bg-surface-2 focus:outline-none"
            >
              <option value="p">{t('paragraph')}</option>
              {[2, 3, 4].map((level) => (
                <option key={level} value={`h${level}`}>
                  {t('heading', { level })}
                </option>
              ))}
            </select>
            <Divider />
            <ToolButton label={t('bold')} active={state.bold} onPress={() => run((chain) => chain.toggleBold())}>
              <Icon name="bold" />
            </ToolButton>
            <ToolButton label={t('italic')} active={state.italic} onPress={() => run((chain) => chain.toggleItalic())}>
              <Icon name="italic" />
            </ToolButton>
            <ToolButton label={t('underline')} active={state.underline} onPress={() => run((chain) => chain.toggleUnderline())}>
              <Icon name="underline" />
            </ToolButton>
            <ToolButton label={t('strike')} active={state.strike} onPress={() => run((chain) => chain.toggleStrike())}>
              <Icon name="strike" />
            </ToolButton>
            <ToolButton label={t('code')} active={state.code} onPress={() => run((chain) => chain.toggleCode())}>
              <Icon name="code" />
            </ToolButton>
            <Divider />
            <ToolButton label={t('bulletList')} active={state.bulletList} onPress={() => run((chain) => chain.toggleBulletList())}>
              <Icon name="list" />
            </ToolButton>
            <ToolButton label={t('orderedList')} active={state.orderedList} onPress={() => run((chain) => chain.toggleOrderedList())}>
              <Icon name="list-ordered" />
            </ToolButton>
            <ToolButton label={t('quote')} active={state.blockquote} onPress={() => run((chain) => chain.toggleBlockquote())}>
              <Icon name="quote" />
            </ToolButton>
            <LinkButton editor={editor} />
            <div className="flex-grow" />
            <ToolButton label={t('undo')} disabled={!state.canUndo} onPress={() => run((chain) => chain.undo())}>
              <Icon name="undo" />
            </ToolButton>
            <ToolButton label={t('redo')} disabled={!state.canRedo} onPress={() => run((chain) => chain.redo())}>
              <Icon name="redo" />
            </ToolButton>
          </div>
        )}
        <div className="relative">
          {state.empty && node.placeholder && <span className="pointer-events-none absolute top-3 left-4 text-[14.5px] text-fg-3">{node.placeholder}</span>}
          <EditorContent editor={editor} />
        </div>
      </div>
    </FieldFrame>
  );
}
