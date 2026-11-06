import Image from '@tiptap/extension-image';
import { TableKit } from '@tiptap/extension-table';
import TextAlign from '@tiptap/extension-text-align';
import { EditorContent, mergeAttributes, Node as TiptapNode, useEditor, useEditorState, type Editor } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import { useEffect, useId, useMemo, useRef, useState, type ReactNode } from 'react';
import { Button as AriaButton, Dialog, DialogTrigger, Popover } from 'react-aria-components';
import { t } from '../i18n';
import { buttonClass, controlClass, cx, Icon, invalidClass } from '../ui';
import type { FieldNode } from '../types';
import { CodeEditor } from './code';
import { FieldFrame, useField } from './fields';

// Tiptap lives in its own chunk, loaded only by screens with a RichText field.

type RichTextNode = FieldNode & { minHeight?: number; embeds?: string[]; htmlMode?: boolean; tags?: string[] };

/** <iframe> players (RuTube, YouTube…) from the hosts the field allows. */
const Iframe = TiptapNode.create({
  name: 'iframe',
  group: 'block',
  atom: true,
  addAttributes() {
    return { src: { default: null }, width: { default: '560' }, height: { default: '315' }, allowfullscreen: { default: 'true' } };
  },
  parseHTML() {
    return [{ tag: 'iframe' }];
  },
  renderHTML({ HTMLAttributes }) {
    return ['iframe', mergeAttributes({ frameborder: '0', allow: 'fullscreen' }, HTMLAttributes)];
  },
});

const hostAllowed = (url: string, hosts: string[]) => {
  try {
    const { host, protocol } = new URL(url);
    return protocol === 'https:' && hosts.some((allowed) => host === allowed || host.endsWith(`.${allowed}`));
  } catch {
    return false;
  }
};

/**
 * Markup the visual editor would drop: tags it does not know, inline styles other than alignment,
 * classes. Such text opens in HTML mode instead of being changed silently.
 */
function lossyMarkup(html: string, tags: string[]): string[] {
  if (!html || typeof DOMParser === 'undefined') return [];
  const found = new Set<string>();
  const doc = new DOMParser().parseFromString(`<div>${html}</div>`, 'text/html');
  doc.body.querySelectorAll('*').forEach((element) => {
    const tag = element.tagName.toLowerCase();
    if (tag !== 'div' && tag !== 'span' && tag !== 'iframe' && !tags.includes(tag)) found.add(`<${tag}>`);
    if ((tag === 'div' || tag === 'span') && element.attributes.length > 0) found.add(`<${tag}>`);
    const style = element.getAttribute('style') ?? '';
    if (style && style.split(';').some((rule) => rule.trim() && !/^\s*(text-align|width|min-width)\s*:/i.test(rule))) found.add('style');
    if (element.hasAttribute('class')) found.add('class');
  });
  return [...found];
}

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

/** A toolbar button that asks for an address: link, image, video. */
function UrlButton({ icon, label, active, initial, hint, onApply, onRemove }: { icon: string; label: string; active?: boolean; initial?: () => string; hint?: string; onApply: (url: string) => void; onRemove?: () => void }) {
  const [url, setUrl] = useState('');
  const [error, setError] = useState<string | null>(null);

  return (
    <DialogTrigger
      onOpenChange={(open) => {
        if (open) {
          setUrl(initial?.() ?? '');
          setError(null);
        }
      }}
    >
      <AriaButton aria-label={label} className={cx('grid size-7 place-items-center rounded-md text-fg-2 hover:bg-surface-2 hover:text-fg', active && 'bg-accent-soft text-accent-fg')}>
        <Icon name={icon} />
      </AriaButton>
      <Popover placement="bottom start" className="w-80 rounded-xl border border-line bg-surface p-3 shadow-pop outline-none">
        <Dialog aria-label={label} className="outline-none">
          {({ close }) => (
            <form
              className="flex flex-col gap-2"
              onSubmit={(event) => {
                event.preventDefault();
                try {
                  onApply(url.trim());
                  close();
                } catch (reason) {
                  setError(reason instanceof Error ? reason.message : String(reason));
                }
              }}
            >
              <label className="text-[13px] font-medium text-fg-2">
                {label}
                <input autoFocus value={url} onChange={(event) => setUrl(event.target.value)} placeholder="https://" className={cx(controlClass, 'mt-1.5')} />
              </label>
              {error ? <span className="text-[12.5px] text-danger-fg">{error}</span> : hint && <span className="text-[12.5px] text-fg-3">{hint}</span>}
              <div className="flex justify-end gap-2">
                {active && onRemove && (
                  <button
                    type="button"
                    className={buttonClass('ghost', 'sm')}
                    onClick={() => {
                      onRemove();
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

export function RichTextField({ node }: { node: RichTextNode }) {
  const id = useId();
  const field = useField(node);
  const value = typeof field.value === 'string' ? field.value : '';
  const embeds = useMemo(() => node.embeds ?? [], [node.embeds]);
  const tags = useMemo(() => node.tags ?? [], [node.tags]);
  const lossy = useMemo(() => lossyMarkup(value, tags), [value, tags]);
  const [mode, setMode] = useState<'visual' | 'html'>(() => (node.htmlMode !== false && lossy.length > 0 ? 'html' : 'visual'));
  // The HTML this editor last produced or received; anything else in the state came from the server.
  const known = useRef(value);
  // What the editor turns the loaded text into: the same output means "not changed", whatever the markup.
  const normalized = useRef<string | null>(null);

  const editor = useEditor({
    extensions: [
      StarterKit.configure({ heading: { levels: [2, 3, 4] }, link: { openOnClick: false, autolink: true, defaultProtocol: 'https' } }),
      TextAlign.configure({ types: ['heading', 'paragraph'] }),
      TableKit.configure({ table: { resizable: false } }),
      Image,
      ...(embeds.length > 0 ? [Iframe] : []),
    ],
    content: value,
    editable: !field.disabled,
    immediatelyRender: true,
    onCreate: ({ editor: current }) => {
      normalized.current = current.isEmpty ? '' : current.getHTML();
    },
    onUpdate: ({ editor: current }) => {
      const html = current.isEmpty ? '' : current.getHTML();
      // Tiptap rewrites markup on load (lists, a trailing paragraph); that is not an edit.
      const next = html === normalized.current ? known.current : html;
      if (next === known.current) return;
      known.current = next;
      field.setValue(next);
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
      bulletList: current.isActive('bulletList'),
      orderedList: current.isActive('orderedList'),
      blockquote: current.isActive('blockquote'),
      link: current.isActive('link'),
      table: current.isActive('table'),
      align: current.isActive({ textAlign: 'center' }) ? 'center' : current.isActive({ textAlign: 'right' }) ? 'right' : 'left',
      empty: current.isEmpty,
      canUndo: current.can().undo(),
      canRedo: current.can().redo(),
    }),
  });

  const load = (html: string) => {
    known.current = html;
    editor.commands.setContent(html, { emitUpdate: false });
    normalized.current = editor.isEmpty ? '' : editor.getHTML();
  };

  // Text set by the server (a reaction or a refresh) replaces the content.
  // isDestroyed is also true before the editor is mounted; the initial content comes from the options.
  useEffect(() => {
    if (value === known.current || editor.isDestroyed) return;
    load(value);
    // load() only touches the editor and refs.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [editor, value]);

  useEffect(() => {
    if (!editor.isDestroyed) editor.setEditable(!field.disabled);
  }, [editor, field.disabled]);

  const run = (command: (chain: ReturnType<Editor['chain']>) => ReturnType<Editor['chain']>) => command(editor.chain().focus()).run();

  const toVisual = () => {
    if (lossy.length > 0 && !window.confirm(t('richLossConfirm', { what: lossy.join(', ') }))) return;
    load(value);
    setMode('visual');
  };

  const tableButton = (label: string, action: (chain: ReturnType<Editor['chain']>) => ReturnType<Editor['chain']>) => (
    <button type="button" onMouseDown={(event) => event.preventDefault()} onClick={() => run(action)} className={buttonClass('ghost', 'sm')}>
      {label}
    </button>
  );

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
            {mode === 'visual' && (
              <>
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
                <Divider />
                {(['left', 'center', 'right'] as const).map((align) => (
                  <ToolButton key={align} label={t(`align_${align}`)} active={state.align === align} onPress={() => run((chain) => chain.setTextAlign(align))}>
                    <Icon name={`align-${align}`} />
                  </ToolButton>
                ))}
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
                <UrlButton
                  icon="link"
                  label={t('linkUrl')}
                  active={state.link}
                  initial={() => String(editor.getAttributes('link').href ?? '')}
                  onApply={(href) => {
                    const chain = editor.chain().focus().extendMarkRange('link');
                    (href ? chain.setLink({ href }) : chain.unsetLink()).run();
                  }}
                  onRemove={() => editor.chain().focus().extendMarkRange('link').unsetLink().run()}
                />
                <UrlButton icon="image" label={t('imageUrl')} onApply={(src) => src && run((chain) => chain.setImage({ src }))} />
                {embeds.length > 0 && (
                  <UrlButton
                    icon="video"
                    label={t('videoUrl')}
                    hint={t('videoHosts', { hosts: embeds.join(', ') })}
                    onApply={(src) => {
                      if (!hostAllowed(src, embeds)) throw new Error(t('videoHosts', { hosts: embeds.join(', ') }));
                      run((chain) => chain.insertContent({ type: 'iframe', attrs: { src } }));
                    }}
                  />
                )}
                <ToolButton label={t('table')} active={state.table} onPress={() => run((chain) => chain.insertTable({ rows: 3, cols: 3, withHeaderRow: true }))}>
                  <Icon name="table" />
                </ToolButton>
                {state.table && (
                  <>
                    {tableButton(t('addRow'), (chain) => chain.addRowAfter())}
                    {tableButton(t('addColumn'), (chain) => chain.addColumnAfter())}
                    {tableButton(t('deleteRow'), (chain) => chain.deleteRow())}
                    {tableButton(t('deleteTable'), (chain) => chain.deleteTable())}
                  </>
                )}
              </>
            )}
            <div className="flex-grow" />
            {mode === 'visual' && (
              <>
                <ToolButton label={t('undo')} disabled={!state.canUndo} onPress={() => run((chain) => chain.undo())}>
                  <Icon name="undo" />
                </ToolButton>
                <ToolButton label={t('redo')} disabled={!state.canRedo} onPress={() => run((chain) => chain.redo())}>
                  <Icon name="redo" />
                </ToolButton>
              </>
            )}
            {node.htmlMode !== false && (
              <button
                type="button"
                aria-pressed={mode === 'html'}
                onClick={() => (mode === 'html' ? toVisual() : setMode('html'))}
                className={cx(buttonClass('ghost', 'sm'), mode === 'html' && 'bg-accent-soft text-accent-fg')}
              >
                <Icon name="file-code" />
                HTML
              </button>
            )}
          </div>
        )}
        {mode === 'html' && lossy.length > 0 && (
          <p className="border-b border-line bg-warning-soft px-4 py-2 text-[12.5px] text-warning-fg">{t('richLossNotice', { what: lossy.join(', ') })}</p>
        )}
        <div className="relative" hidden={mode !== 'visual'}>
          {state.empty && node.placeholder && <span className="pointer-events-none absolute top-3 left-4 text-[14.5px] text-fg-3">{node.placeholder}</span>}
          <EditorContent editor={editor} />
        </div>
        {mode === 'html' && (
          <CodeEditor
            id={`${id}-html`}
            label={node.label ?? node.path}
            value={value}
            onChange={(html) => {
              known.current = html;
              field.setValue(html);
            }}
            language="html"
            minHeight={node.minHeight ?? 200}
            lineNumbers
            wrap
            disabled={field.disabled}
          />
        )}
      </div>
    </FieldFrame>
  );
}
