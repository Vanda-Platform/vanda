import { CharacterCount } from '@tiptap/extensions';
import Subscript from '@tiptap/extension-subscript';
import Superscript from '@tiptap/extension-superscript';
import { TableKit } from '@tiptap/extension-table';
import TextAlign from '@tiptap/extension-text-align';
import { TextStyleKit } from '@tiptap/extension-text-style';
import { EditorContent, useEditor, useEditorState, type Editor } from '@tiptap/react';
import { BubbleMenu } from '@tiptap/react/menus';
import StarterKit from '@tiptap/starter-kit';
import { useEffect, useId, useMemo, useRef, useState, type ReactNode } from 'react';
import { Button as AriaButton, Dialog, DialogTrigger, Heading, Modal, ModalOverlay, Popover } from 'react-aria-components';
import { t } from '../i18n';
import { useStore } from '../store';
import { upload } from '../transport';
import { buttonClass, controlClass, cx, Icon, invalidClass, MOBILE_QUERY, useMediaQuery } from '../ui';
import type { FieldNode } from '../types';
import { CodeEditor } from './code';
import { FieldFrame, useField } from './fields';
import {
  cleanPastedHtml,
  goToMatch,
  Iframe,
  replaceAll,
  replaceMatch,
  searchState,
  SearchReplace,
  setSearchTerm,
  SlashCommand,
  VandaImage,
  type ImageAlign,
  type SlashItem,
  type SlashView,
} from './richtext-extensions';
import { formatSize } from './upload';

// Tiptap lives in its own chunk, loaded only by screens with a RichText field.

type RichTextNode = FieldNode & {
  minHeight?: number;
  embeds?: string[];
  htmlMode?: boolean;
  tags?: string[];
  /** Tag => attributes Vanda\Support\Html keeps. */
  elements?: Record<string, string[]>;
  /** CSS properties Vanda\Support\Html keeps. */
  styles?: string[];
  /** Bytes per image uploaded into the text (RichText::uploads()); missing when off. */
  uploads?: number | null;
};

/** The editor keeps an empty paragraph at the end so there is a place to type after a table or a list. */
const withoutTrailingParagraphs = (html: string) => html.replace(/(<p><\/p>)+$/, '');

const COLORS = ['#18181b', '#71717a', '#dc2626', '#ea580c', '#ca8a04', '#16a34a', '#0d9488', '#2563eb', '#7c3aed', '#db2777'];
const HIGHLIGHTS = ['#fef08a', '#fed7aa', '#fecaca', '#bbf7d0', '#bfdbfe', '#e9d5ff'];
const SIZES = ['12px', '14px', '16px', '18px', '20px', '24px', '30px'];

const hostAllowed = (url: string, hosts: string[]) => {
  try {
    const { host, protocol } = new URL(url);
    return protocol === 'https:' && hosts.some((allowed) => host === allowed || host.endsWith(`.${allowed}`));
  } catch {
    return false;
  }
};

/**
 * Markup the editor would drop: tags, attributes and styles the server does not keep, or that
 * the editor cannot show. Such text opens in HTML mode instead of being changed silently.
 */
function lossyMarkup(html: string, elements: Record<string, string[]>, styles: string[]): string[] {
  if (!html || typeof DOMParser === 'undefined') return [];
  const found = new Set<string>();
  const doc = new DOMParser().parseFromString(`<div>${html}</div>`, 'text/html');
  doc.body.querySelectorAll('div *').forEach((element) => {
    const tag = element.tagName.toLowerCase();
    const allowed = elements[tag];
    // A bare <div> only wraps: its text stays, so it is no reason for HTML mode.
    if (tag === 'div' && element.attributes.length === 0) return;
    if (!allowed) {
      found.add(`<${tag}>`);
      return;
    }
    for (const attribute of [...element.attributes]) {
      const name = attribute.name.toLowerCase();
      if (name === 'style') {
        const rules = attribute.value
          .split(';')
          .map((rule) => rule.split(':')[0]?.trim().toLowerCase() ?? '')
          .filter(Boolean);
        if (rules.some((rule) => !styles.includes(rule))) found.add('style');
      } else if (!allowed.includes(name)) {
        found.add(name === 'class' ? 'class' : `${tag}[${name}]`);
      }
    }
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
      className={cx(
        'grid size-8 shrink-0 place-items-center rounded-md text-fg-2 hover:bg-surface-2 hover:text-fg disabled:opacity-40 md:size-7',
        active && 'bg-accent-soft text-accent-fg hover:bg-accent-soft hover:text-accent-fg',
      )}
    >
      {children}
    </button>
  );
}

const Divider = () => <span aria-hidden="true" className="mx-1 h-5 w-px shrink-0 bg-line" />;

const selectClass = 'h-8 shrink-0 rounded-md bg-transparent px-1.5 text-[13px] font-medium text-fg-2 hover:bg-surface-2 focus:outline-none md:h-7';

/** Text colour and highlight, one popover with two palettes. */
function ColorPicker({ editor, color, background }: { editor: Editor; color?: string; background?: string }) {
  const swatch = (value: string, active: boolean, apply: () => void, label: string) => (
    <button
      key={value}
      type="button"
      aria-label={`${label} ${value}`}
      aria-pressed={active}
      onMouseDown={(event) => event.preventDefault()}
      onClick={apply}
      className={cx('size-6 rounded-md border border-black/10', active && 'ring-2 ring-accent ring-offset-1 ring-offset-surface')}
      style={{ background: value }}
    />
  );

  return (
    <DialogTrigger>
      <AriaButton aria-label={t('textColor')} className="relative grid size-8 shrink-0 place-items-center rounded-md text-fg-2 hover:bg-surface-2 hover:text-fg md:size-7">
        <Icon name="palette" />
        <span aria-hidden="true" className="absolute right-1.5 bottom-1 left-1.5 h-0.5 rounded-full" style={{ background: color ?? background ?? 'transparent' }} />
      </AriaButton>
      <Popover placement="bottom start" className="w-60 rounded-xl border border-line bg-surface p-3 shadow-pop outline-none">
        <Dialog aria-label={t('textColor')} className="flex flex-col gap-3 outline-none">
          {({ close }) => (
            <>
              <div className="flex flex-col gap-1.5">
                <span className="text-[12.5px] font-medium text-fg-3">{t('textColor')}</span>
                <div className="flex flex-wrap gap-1.5">
                  {COLORS.map((value) => swatch(value, color === value, () => (editor.chain().focus().setColor(value).run(), close()), t('textColor')))}
                </div>
                <button type="button" className="self-start text-[12.5px] text-fg-2 hover:text-fg hover:underline" onClick={() => (editor.chain().focus().unsetColor().run(), close())}>
                  {t('defaultColor')}
                </button>
              </div>
              <div className="flex flex-col gap-1.5">
                <span className="text-[12.5px] font-medium text-fg-3">{t('highlight')}</span>
                <div className="flex flex-wrap gap-1.5">
                  {HIGHLIGHTS.map((value) => swatch(value, background === value, () => (editor.chain().focus().setBackgroundColor(value).run(), close()), t('highlight')))}
                </div>
                <button type="button" className="self-start text-[12.5px] text-fg-2 hover:text-fg hover:underline" onClick={() => (editor.chain().focus().unsetBackgroundColor().run(), close())}>
                  {t('defaultColor')}
                </button>
              </div>
            </>
          )}
        </Dialog>
      </Popover>
    </DialogTrigger>
  );
}

type InsertKind = 'link' | 'image' | 'video';

/** Address of a link, an image (or an upload) or a video, in one small dialog. */
function InsertDialog({
  kind,
  editor,
  embeds,
  uploads,
  onUpload,
  onClose,
}: {
  kind: InsertKind;
  editor: Editor;
  embeds: string[];
  uploads?: number | null;
  onUpload: (files: File[]) => void;
  onClose: () => void;
}) {
  const link = kind === 'link' ? (editor.getAttributes('link') as { href?: string; target?: string }) : {};
  const [url, setUrl] = useState(link.href ?? '');
  const [alt, setAlt] = useState('');
  const [newTab, setNewTab] = useState(link.target === '_blank');
  const [error, setError] = useState<string | null>(null);
  const file = useRef<HTMLInputElement>(null);
  const title = kind === 'link' ? t('linkUrl') : kind === 'image' ? t('insertImage') : t('insertVideo');

  const apply = () => {
    const address = url.trim();
    if (kind === 'link') {
      const chain = editor.chain().focus().extendMarkRange('link');
      (address ? chain.setLink({ href: address, target: newTab ? '_blank' : null }) : chain.unsetLink()).run();
    } else if (kind === 'image') {
      if (!address) return setError(t('imageUrl'));
      editor.chain().focus().setImage({ src: address, alt: alt.trim() || undefined }).run();
    } else {
      if (!hostAllowed(address, embeds)) return setError(t('videoHosts', { hosts: embeds.join(', ') }));
      editor.chain().focus().insertContent({ type: 'iframe', attrs: { src: address } }).run();
    }
    onClose();
  };

  return (
    <ModalOverlay isOpen isDismissable onOpenChange={(open) => !open && onClose()} className="fixed inset-0 z-50 grid bg-(--v-scrim) max-md:items-end md:place-items-center md:p-4">
      <Modal className="w-full border border-line bg-surface shadow-pop outline-none max-md:rounded-t-2xl md:max-w-[440px] md:rounded-xl">
        <Dialog aria-label={title} className="outline-none">
          <form
            className="flex flex-col gap-3 p-5"
            onSubmit={(event) => {
              event.preventDefault();
              apply();
            }}
          >
            <Heading slot="title" className="text-[16px] font-semibold">
              {title}
            </Heading>
            {kind === 'image' && uploads && (
              <>
                <button type="button" className={cx(buttonClass('secondary'), 'justify-center')} onClick={() => file.current?.click()}>
                  <Icon name="upload" />
                  {t('uploadFromComputer')}
                  <span className="text-fg-3">· {t('upTo', { size: formatSize(uploads) })}</span>
                </button>
                <input
                  ref={file}
                  type="file"
                  accept="image/*"
                  multiple
                  className="sr-only"
                  onChange={(event) => {
                    const files = [...(event.target.files ?? [])];
                    if (files.length > 0) {
                      onUpload(files);
                      onClose();
                    }
                  }}
                />
                <span className="text-center text-[12.5px] text-fg-3">{t('orByUrl')}</span>
              </>
            )}
            <input autoFocus={!(kind === 'image' && uploads)} value={url} onChange={(event) => setUrl(event.target.value)} placeholder="https://" aria-label={title} className={controlClass} />
            {kind === 'image' && <input value={alt} onChange={(event) => setAlt(event.target.value)} placeholder={t('altText')} aria-label={t('altText')} className={controlClass} />}
            {kind === 'link' && (
              <label className="flex items-center gap-2 text-[13.5px]">
                <input type="checkbox" className="vanda-check" checked={newTab} onChange={(event) => setNewTab(event.target.checked)} />
                {t('linkNewTab')}
              </label>
            )}
            {error ? <span className="text-[12.5px] text-danger-fg">{error}</span> : kind === 'video' && <span className="text-[12.5px] text-fg-3">{t('videoHosts', { hosts: embeds.join(', ') })}</span>}
            <div className="flex justify-end gap-2 pt-1">
              {kind === 'link' && link.href && (
                <button
                  type="button"
                  className={cx(buttonClass('ghost'), 'mr-auto')}
                  onClick={() => {
                    editor.chain().focus().extendMarkRange('link').unsetLink().run();
                    onClose();
                  }}
                >
                  {t('remove')}
                </button>
              )}
              <button type="button" className={buttonClass('secondary')} onClick={onClose}>
                {t('cancel')}
              </button>
              <button type="submit" className={buttonClass('primary')}>
                {kind === 'link' ? t('apply') : t('insert')}
              </button>
            </div>
          </form>
        </Dialog>
      </Modal>
    </ModalOverlay>
  );
}

/** Find (and replace) inside the text: matches are highlighted, Enter goes to the next one. */
function FindBar({ editor, onClose }: { editor: Editor; onClose: () => void }) {
  const [replacement, setReplacement] = useState('');
  const search = useEditorState({ editor, selector: ({ editor: current }) => ({ term: searchState(current).term, total: searchState(current).results.length, index: searchState(current).index }) });

  useEffect(() => () => setSearchTerm(editor, ''), [editor]);

  return (
    <div className="flex flex-wrap items-center gap-2 border-b border-line bg-surface-2/40 px-2 py-1.5" role="search">
      <input
        autoFocus
        value={search.term}
        onChange={(event) => setSearchTerm(editor, event.target.value)}
        onKeyDown={(event) => {
          if (event.key === 'Enter') {
            event.preventDefault();
            goToMatch(editor, event.shiftKey ? -1 : 1);
          }
          if (event.key === 'Escape') onClose();
        }}
        placeholder={t('find')}
        aria-label={t('find')}
        className={cx(controlClass, 'h-8 w-44 min-w-0 flex-1 md:flex-none')}
      />
      <span className="min-w-16 text-[12.5px] text-fg-3 tabular-nums">{search.term ? (search.total > 0 ? t('matchOf', { index: search.index + 1, total: search.total }) : t('noMatches')) : ''}</span>
      <ToolButton label={t('previousMatch')} disabled={search.total === 0} onPress={() => goToMatch(editor, -1)}>
        <Icon name="chevron-up" />
      </ToolButton>
      <ToolButton label={t('nextMatch')} disabled={search.total === 0} onPress={() => goToMatch(editor, 1)}>
        <Icon name="chevron-down" />
      </ToolButton>
      <input
        value={replacement}
        onChange={(event) => setReplacement(event.target.value)}
        onKeyDown={(event) => event.key === 'Escape' && onClose()}
        placeholder={t('replaceWith')}
        aria-label={t('replaceWith')}
        className={cx(controlClass, 'h-8 w-44 min-w-0 flex-1 md:flex-none')}
      />
      <button type="button" className={buttonClass('ghost', 'sm')} disabled={search.total === 0} onClick={() => replaceMatch(editor, replacement)}>
        {t('replace')}
      </button>
      <button type="button" className={buttonClass('ghost', 'sm')} disabled={search.total === 0} onClick={() => replaceAll(editor, replacement)}>
        {t('replaceAll')}
      </button>
      <span className="flex-1" />
      <ToolButton label={t('close')} onPress={onClose}>
        <Icon name="x" />
      </ToolButton>
    </div>
  );
}

interface Tool {
  id: string;
  label: string;
  icon: string;
  active?: boolean;
  disabled?: boolean;
  run: () => void;
}

/** The narrow toolbar's «⋯»: every tool that did not fit, with its name. */
function Overflow({ tools, extra }: { tools: Tool[]; extra: ReactNode }) {
  return (
    <DialogTrigger>
      <AriaButton aria-label={t('moreTools')} className="grid size-8 shrink-0 place-items-center rounded-md text-fg-2 hover:bg-surface-2 hover:text-fg md:size-7">
        <Icon name="more" />
      </AriaButton>
      <Popover placement="bottom end" className="max-h-[70dvh] w-64 overflow-y-auto rounded-xl border border-line bg-surface p-1.5 shadow-pop outline-none">
        <Dialog aria-label={t('moreTools')} className="flex flex-col outline-none">
          {({ close }) => (
            <>
              <div className="px-1.5 pt-1 pb-2">{extra}</div>
              {tools.map((tool) => (
                <button
                  key={tool.id}
                  type="button"
                  disabled={tool.disabled}
                  onMouseDown={(event) => event.preventDefault()}
                  onClick={() => {
                    tool.run();
                    close();
                  }}
                  className={cx('flex h-9 items-center gap-2.5 rounded-lg px-2 text-left text-[14px] hover:bg-surface-2 disabled:opacity-40', tool.active && 'text-accent-fg')}
                >
                  <Icon name={tool.icon} />
                  {tool.label}
                </button>
              ))}
            </>
          )}
        </Dialog>
      </Popover>
    </DialogTrigger>
  );
}

/** Width of an element, followed: the toolbar folds when the field is narrow, not only on phones. */
function useWidth<T extends HTMLElement>(): [React.RefObject<T | null>, number] {
  const ref = useRef<T>(null);
  const [width, setWidth] = useState(0);
  useEffect(() => {
    if (!ref.current) return;
    const observer = new ResizeObserver(([entry]) => setWidth(entry?.contentRect.width ?? 0));
    observer.observe(ref.current);
    return () => observer.disconnect();
  }, []);
  return [ref, width];
}

export function RichTextField({ node }: { node: RichTextNode }) {
  const id = useId();
  const store = useStore();
  const field = useField(node);
  const mobile = useMediaQuery(MOBILE_QUERY);
  const [frame, width] = useWidth<HTMLDivElement>();
  const compact = mobile || (width > 0 && width < 760);
  const value = typeof field.value === 'string' ? field.value : '';
  const embeds = useMemo(() => node.embeds ?? [], [node.embeds]);
  const elements = useMemo(() => node.elements ?? Object.fromEntries((node.tags ?? []).map((tag) => [tag, []])), [node.elements, node.tags]);
  const styles = useMemo(() => node.styles ?? ['text-align', 'width', 'min-width'], [node.styles]);
  const lossy = useMemo(() => lossyMarkup(value, elements, styles), [value, elements, styles]);
  const [mode, setMode] = useState<'visual' | 'html'>(() => (node.htmlMode !== false && lossy.length > 0 ? 'html' : 'visual'));
  const [slash, setSlash] = useState<SlashView | null>(null);
  const [dialog, setDialog] = useState<InsertKind | null>(null);
  const [finding, setFinding] = useState(false);
  const [fullscreen, setFullscreen] = useState(false);
  const [uploading, setUploading] = useState<number | null>(null);
  const [notice, setNotice] = useState<string | null>(null);
  // The HTML this editor last produced or received; anything else in the state came from the server.
  const known = useRef(value);
  // What the editor turns the loaded text into: the same output means "not changed", whatever the markup.
  const normalized = useRef<string | null>(null);
  // Handlers the editor (created once) calls; they always see the latest render.
  const handlers = useRef({ uploadImages: (_files: File[], _at?: number) => {}, slashItems: (): SlashItem[] => [], find: () => {} });

  const editor = useEditor({
    extensions: [
      StarterKit.configure({ heading: { levels: [2, 3, 4] }, link: { openOnClick: false, autolink: true, defaultProtocol: 'https' } }),
      TextAlign.configure({ types: ['heading', 'paragraph'] }),
      TextStyleKit.configure({ fontFamily: false, lineHeight: false }),
      Subscript,
      Superscript,
      TableKit.configure({ table: { resizable: true, lastColumnResizable: false } }),
      VandaImage,
      CharacterCount,
      SearchReplace,
      SlashCommand(() => handlers.current.slashItems(), setSlash),
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
      // Tiptap rewrites markup on load (lists, a trailing paragraph); that is not an edit. The empty
      // paragraph it adds after a list or table can come after onCreate, so it is left out of the comparison.
      const next = withoutTrailingParagraphs(html) === withoutTrailingParagraphs(normalized.current ?? '') ? known.current : html;
      if (next === known.current) return;
      known.current = next;
      field.setValue(next);
    },
    editorProps: {
      attributes: { id, 'aria-label': node.label ?? node.path, class: 'vanda-prose vanda-editor px-4 py-3', style: `min-height: ${node.minHeight ?? 200}px` },
      // Word and Google Docs bring fonts, classes and mso-* styles: only what the server keeps stays.
      transformPastedHTML: (html) => cleanPastedHtml(html, elements),
      handlePaste: (_view, event) => {
        const files = [...(event.clipboardData?.files ?? [])].filter((file) => file.type.startsWith('image/'));
        if (files.length === 0 || !node.uploads) return false;
        handlers.current.uploadImages(files);
        return true;
      },
      handleDrop: (view, event, _slice, moved) => {
        const files = [...(event.dataTransfer?.files ?? [])].filter((file) => file.type.startsWith('image/'));
        if (moved || files.length === 0 || !node.uploads) return false;
        handlers.current.uploadImages(files, view.posAtCoords({ left: event.clientX, top: event.clientY })?.pos);
        return true;
      },
      handleKeyDown: (_view, event) => {
        if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'f') {
          handlers.current.find();
          return true;
        }
        return false;
      },
    },
  });

  const state = useEditorState({
    editor,
    selector: ({ editor: current }) => ({
      block: current.isActive('heading', { level: 2 }) ? 'h2' : current.isActive('heading', { level: 3 }) ? 'h3' : current.isActive('heading', { level: 4 }) ? 'h4' : current.isActive('codeBlock') ? 'pre' : 'p',
      bold: current.isActive('bold'),
      italic: current.isActive('italic'),
      underline: current.isActive('underline'),
      strike: current.isActive('strike'),
      subscript: current.isActive('subscript'),
      superscript: current.isActive('superscript'),
      bulletList: current.isActive('bulletList'),
      orderedList: current.isActive('orderedList'),
      blockquote: current.isActive('blockquote'),
      link: current.isActive('link'),
      table: current.isActive('table'),
      image: current.isActive('image'),
      imageAlign: (current.getAttributes('image').align as ImageAlign | null) ?? null,
      color: current.getAttributes('textStyle').color as string | undefined,
      background: current.getAttributes('textStyle').backgroundColor as string | undefined,
      fontSize: (current.getAttributes('textStyle').fontSize as string | undefined) ?? '',
      align: (['center', 'right', 'justify'] as const).find((align) => current.isActive({ textAlign: align })) ?? 'left',
      empty: current.isEmpty,
      canUndo: current.can().undo(),
      canRedo: current.can().redo(),
      canMerge: current.can().mergeCells(),
      canSplit: current.can().splitCell(),
      characters: (current.storage as unknown as { characterCount?: { characters: () => number } }).characterCount?.characters() ?? 0,
      words: (current.storage as unknown as { characterCount?: { words: () => number } }).characterCount?.words() ?? 0,
    }),
  });

  const run = (command: (chain: ReturnType<Editor['chain']>) => ReturnType<Editor['chain']>) => command(editor.chain().focus()).run();

  const uploadImages = async (files: File[], at?: number) => {
    setNotice(null);
    for (const file of files) {
      if (node.uploads && file.size > node.uploads) {
        setNotice(t('imageTooLarge', { max: formatSize(node.uploads) }));
        continue;
      }
      setUploading(0);
      try {
        const { status, data } = await upload(store.get().page, node.path, file, (fraction) => setUploading(Math.round(fraction * 100))).promise;
        if (status !== 200) {
          setNotice(data.errors?.[node.path]?.[0] ?? t('uploadFailed'));
          continue;
        }
        const image = { type: 'image', attrs: { src: String(data.value), alt: file.name.replace(/\.[^.]+$/, '') } };
        (at === undefined ? editor.chain().focus().insertContent(image) : editor.chain().focus().insertContentAt(at, image)).run();
      } catch {
        setNotice(t('uploadFailed'));
      } finally {
        setUploading(null);
      }
    }
  };

  const slashItems = (): SlashItem[] => [
    ...([2, 3, 4] as const).map((level) => ({
      id: `h${level}`,
      label: t('heading', { level }),
      icon: `heading-${level}`,
      keywords: `h${level} heading заголовок`,
      run: (current: Editor) => current.chain().focus().setHeading({ level }).run(),
    })),
    { id: 'bullet', label: t('bulletList'), icon: 'list', keywords: 'ul list список', run: (current) => current.chain().focus().toggleBulletList().run() },
    { id: 'ordered', label: t('orderedList'), icon: 'list-ordered', keywords: 'ol number список нумерованный', run: (current) => current.chain().focus().toggleOrderedList().run() },
    { id: 'quote', label: t('quote'), icon: 'quote', keywords: 'blockquote цитата', run: (current) => current.chain().focus().toggleBlockquote().run() },
    { id: 'code', label: t('codeBlock'), icon: 'code-block', keywords: 'code pre код', run: (current) => current.chain().focus().toggleCodeBlock().run() },
    { id: 'table', label: t('table'), icon: 'table', keywords: 'table таблица', run: (current) => current.chain().focus().insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run() },
    { id: 'image', label: t('insertImage'), icon: 'image', keywords: 'image img картинка изображение фото', run: () => setDialog('image') },
    ...(embeds.length > 0 ? [{ id: 'video', label: t('insertVideo'), icon: 'video', keywords: 'video youtube rutube видео', run: () => setDialog('video') }] : []),
    { id: 'divider', label: t('divider'), icon: 'minus', keywords: 'hr divider разделитель линия', run: (current) => current.chain().focus().setHorizontalRule().run() },
  ];

  handlers.current = { uploadImages: (files, at) => void uploadImages(files, at), slashItems, find: () => setFinding(true) };

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

  useEffect(() => {
    if (!fullscreen) return;
    const onKey = (event: KeyboardEvent) => event.key === 'Escape' && !dialog && setFullscreen(false);
    const overflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    window.addEventListener('keydown', onKey);
    return () => {
      document.body.style.overflow = overflow;
      window.removeEventListener('keydown', onKey);
    };
  }, [fullscreen, dialog]);

  useEffect(() => {
    if (!notice) return;
    const timer = window.setTimeout(() => setNotice(null), 6000);
    return () => window.clearTimeout(timer);
  }, [notice]);

  const toVisual = () => {
    if (lossy.length > 0 && !window.confirm(t('richLossConfirm', { what: lossy.join(', ') }))) return;
    load(value);
    setMode('visual');
  };

  const tool = (id: string, label: string, icon: string, active: boolean | undefined, action: () => void, disabled = false): Tool => ({ id, label, icon, active, disabled, run: action });

  const tools: Record<string, Tool> = {
    undo: tool('undo', t('undo'), 'undo', false, () => run((chain) => chain.undo()), !state.canUndo),
    redo: tool('redo', t('redo'), 'redo', false, () => run((chain) => chain.redo()), !state.canRedo),
    bold: tool('bold', t('bold'), 'bold', state.bold, () => run((chain) => chain.toggleBold())),
    italic: tool('italic', t('italic'), 'italic', state.italic, () => run((chain) => chain.toggleItalic())),
    underline: tool('underline', t('underline'), 'underline', state.underline, () => run((chain) => chain.toggleUnderline())),
    strike: tool('strike', t('strike'), 'strike', state.strike, () => run((chain) => chain.toggleStrike())),
    subscript: tool('subscript', t('subscript'), 'subscript', state.subscript, () => run((chain) => chain.toggleSubscript())),
    superscript: tool('superscript', t('superscript'), 'superscript', state.superscript, () => run((chain) => chain.toggleSuperscript())),
    clear: tool('clear', t('clearFormatting'), 'eraser', false, () => run((chain) => chain.unsetAllMarks().clearNodes())),
    alignLeft: tool('alignLeft', t('align_left'), 'align-left', state.align === 'left', () => run((chain) => chain.setTextAlign('left'))),
    alignCenter: tool('alignCenter', t('align_center'), 'align-center', state.align === 'center', () => run((chain) => chain.setTextAlign('center'))),
    alignRight: tool('alignRight', t('align_right'), 'align-right', state.align === 'right', () => run((chain) => chain.setTextAlign('right'))),
    alignJustify: tool('alignJustify', t('align_justify'), 'align-justify', state.align === 'justify', () => run((chain) => chain.setTextAlign('justify'))),
    bulletList: tool('bulletList', t('bulletList'), 'list', state.bulletList, () => run((chain) => chain.toggleBulletList())),
    orderedList: tool('orderedList', t('orderedList'), 'list-ordered', state.orderedList, () => run((chain) => chain.toggleOrderedList())),
    quote: tool('quote', t('quote'), 'quote', state.blockquote, () => run((chain) => chain.toggleBlockquote())),
    divider: tool('divider', t('divider'), 'minus', false, () => run((chain) => chain.setHorizontalRule())),
    link: tool('link', t('linkUrl'), 'link', state.link, () => setDialog('link')),
    image: tool('image', t('insertImage'), node.uploads ? 'image-plus' : 'image', false, () => setDialog('image')),
    video: tool('video', t('insertVideo'), 'video', false, () => setDialog('video')),
    table: tool('table', t('table'), 'table', state.table, () => run((chain) => chain.insertTable({ rows: 3, cols: 3, withHeaderRow: true }))),
    find: tool('find', t('findReplace'), 'search', finding, () => setFinding(!finding)),
    fullscreen: tool('fullscreen', fullscreen ? t('exitFullscreen') : t('fullscreen'), fullscreen ? 'minimize' : 'maximize', fullscreen, () => setFullscreen(!fullscreen)),
  };

  // Groups of the wide toolbar; the narrow one shows PRIMARY and folds the rest into «⋯».
  const groups: (keyof typeof tools)[][] = [
    ['undo', 'redo'],
    ['bold', 'italic', 'underline', 'strike', 'subscript', 'superscript'],
    ['alignLeft', 'alignCenter', 'alignRight', 'alignJustify'],
    ['bulletList', 'orderedList', 'quote', 'divider'],
    ['link', 'image', ...(embeds.length > 0 ? (['video'] as const) : []), 'table'],
  ];
  const primary: (keyof typeof tools)[] = ['bold', 'italic', 'link', 'bulletList', 'image'];
  const folded = groups.flat().filter((key) => !primary.includes(key));

  const blockSelect = (
    <select
      aria-label={t('paragraph')}
      value={state.block}
      onChange={(event) => {
        const block = event.target.value;
        run((chain) => (block === 'p' ? chain.setParagraph() : block === 'pre' ? chain.setCodeBlock() : chain.setHeading({ level: Number(block.slice(1)) as 2 | 3 | 4 })));
      }}
      className={selectClass}
    >
      <option value="p">{t('paragraph')}</option>
      {[2, 3, 4].map((level) => (
        <option key={level} value={`h${level}`}>
          {t('heading', { level })}
        </option>
      ))}
      <option value="pre">{t('codeBlock')}</option>
    </select>
  );

  const sizeSelect = (
    <select
      aria-label={t('fontSize')}
      value={state.fontSize}
      onChange={(event) => run((chain) => (event.target.value ? chain.setFontSize(event.target.value) : chain.unsetFontSize()))}
      className={cx(selectClass, compact && 'w-full border border-line')}
    >
      <option value="">{t('defaultSize')}</option>
      {SIZES.map((size) => (
        <option key={size} value={size}>
          {size.replace('px', '')}
        </option>
      ))}
    </select>
  );

  const button = (key: keyof typeof tools) => {
    const item = tools[key]!;
    return (
      <ToolButton key={item.id} label={item.label} active={item.active} disabled={item.disabled} onPress={item.run}>
        <Icon name={item.icon} />
      </ToolButton>
    );
  };

  const tableButton = (label: string, icon: string, action: (chain: ReturnType<Editor['chain']>) => ReturnType<Editor['chain']>, disabled = false) => (
    <button
      type="button"
      disabled={disabled}
      onMouseDown={(event) => event.preventDefault()}
      onClick={() => run(action)}
      className={cx(buttonClass('ghost', 'sm'), 'shrink-0 disabled:opacity-40')}
    >
      <Icon name={icon} />
      {label}
    </button>
  );

  const imageButton = (align: ImageAlign | null, label: string, icon: string) => (
    <ToolButton key={label} label={label} active={state.imageAlign === align} onPress={() => run((chain) => chain.updateAttributes('image', { align }))}>
      <Icon name={icon} />
    </ToolButton>
  );

  return (
    <FieldFrame node={node} inputId={id} required={field.required} error={field.error}>
      <div
        ref={frame}
        className={cx(
          'overflow-hidden border bg-surface shadow-sm transition',
          fullscreen ? 'fixed inset-0 z-40 flex flex-col rounded-none' : 'rounded-lg focus-within:border-accent focus-within:ring-3 focus-within:ring-ring',
          field.error ? invalidClass : 'border-line-strong',
        )}
      >
        {!field.disabled && (
          <div role="toolbar" aria-label={node.label ?? node.path} className="flex flex-wrap items-center gap-0.5 border-b border-line bg-surface-2/60 px-1.5 py-1">
            {mode === 'visual' && (
              <>
                {blockSelect}
                {!compact && sizeSelect}
                <Divider />
                {compact ? (
                  <>
                    {primary.map(button)}
                    <ColorPicker editor={editor} color={state.color} background={state.background} />
                  </>
                ) : (
                  groups.map((group, index) => (
                    <span key={index} className="flex items-center gap-0.5">
                      {group.map(button)}
                      {index === 1 && (
                        <>
                          <ColorPicker editor={editor} color={state.color} background={state.background} />
                          {button('clear')}
                        </>
                      )}
                      {index < groups.length - 1 && <Divider />}
                    </span>
                  ))
                )}
              </>
            )}
            {/* Find, full screen and HTML stay together at the right, also when the row wraps. */}
            <span className="ml-auto flex shrink-0 items-center gap-0.5">
              {mode === 'visual' &&
                (compact ? (
                  <Overflow tools={[...folded, 'clear', 'find', 'fullscreen'].map((key) => tools[key as keyof typeof tools]!)} extra={sizeSelect} />
                ) : (
                  <>
                    {button('find')}
                    {button('fullscreen')}
                  </>
                ))}
              {node.htmlMode !== false && (
                <button
                  type="button"
                  aria-pressed={mode === 'html'}
                  title={t('htmlSource')}
                  onClick={() => (mode === 'html' ? toVisual() : setMode('html'))}
                  className={cx(buttonClass('ghost', 'sm'), 'shrink-0', mode === 'html' && 'bg-accent-soft text-accent-fg')}
                >
                  <Icon name="file-code" />
                  HTML
                </button>
              )}
            </span>
          </div>
        )}

        {mode === 'visual' && state.table && !field.disabled && (
          <div className="flex items-center gap-0.5 overflow-x-auto border-b border-line bg-surface px-1.5 py-1" role="toolbar" aria-label={t('table')}>
            {tableButton(t('addRowBefore'), 'arrow-up', (chain) => chain.addRowBefore())}
            {tableButton(t('addRowAfter'), 'arrow-down', (chain) => chain.addRowAfter())}
            {tableButton(t('deleteRow'), 'minus', (chain) => chain.deleteRow())}
            <Divider />
            {tableButton(t('addColumnBefore'), 'chevron-left', (chain) => chain.addColumnBefore())}
            {tableButton(t('addColumnAfter'), 'chevron-right', (chain) => chain.addColumnAfter())}
            {tableButton(t('deleteColumn'), 'minus', (chain) => chain.deleteColumn())}
            <Divider />
            {tableButton(t('mergeCells'), 'cells-merge', (chain) => chain.mergeCells(), !state.canMerge)}
            {tableButton(t('splitCell'), 'cells-split', (chain) => chain.splitCell(), !state.canSplit)}
            {tableButton(t('headerRow'), 'header-row', (chain) => chain.toggleHeaderRow())}
            {tableButton(t('headerColumn'), 'header-column', (chain) => chain.toggleHeaderColumn())}
            <Divider />
            {tableButton(t('deleteTable'), 'trash', (chain) => chain.deleteTable())}
          </div>
        )}

        {mode === 'visual' && finding && <FindBar editor={editor} onClose={() => (setFinding(false), editor.commands.focus())} />}

        {mode === 'html' && lossy.length > 0 && (
          <p className="border-b border-line bg-warning-soft px-4 py-2 text-[12.5px] text-warning-fg">{t('richLossNotice', { what: lossy.join(', ') })}</p>
        )}

        <div className={cx('relative', fullscreen && 'min-h-0 flex-1 overflow-y-auto')} hidden={mode !== 'visual'}>
          {state.empty && <span className="pointer-events-none absolute top-3 left-4 text-[14.5px] text-fg-3">{node.placeholder ?? t('slashHint')}</span>}
          <EditorContent editor={editor} className={cx(fullscreen && 'mx-auto max-w-[860px]')} />
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

        {mode === 'visual' && (
          <div className="flex items-center gap-3 border-t border-line px-3 py-1.5 text-[12px] text-fg-3">
            <span aria-live="polite" className={cx('min-w-0 flex-1 truncate', notice && 'text-danger-fg')}>
              {notice ?? (uploading !== null ? t('imageUploading', { percent: uploading }) : '')}
            </span>
            <span className="shrink-0 tabular-nums">{t('textCounter', { characters: state.characters, words: state.words })}</span>
          </div>
        )}
      </div>

      {/* A selection gets the most used marks right next to it. */}
      {mode === 'visual' && !field.disabled && (
        <BubbleMenu
          editor={editor}
          shouldShow={({ editor: current, state: view }) => !view.selection.empty && !current.isActive('image') && !current.isActive('codeBlock') && !current.isActive('iframe')}
          className="z-30 flex items-center gap-0.5 rounded-lg border border-line bg-surface p-1 shadow-pop"
        >
          {button('bold')}
          {button('italic')}
          {button('underline')}
          {button('link')}
          <ColorPicker editor={editor} color={state.color} background={state.background} />
          {button('clear')}
        </BubbleMenu>
      )}

      {/* A selected picture: where it sits in the text and its size. */}
      {mode === 'visual' && !field.disabled && (
        <BubbleMenu
          editor={editor}
          pluginKey="vandaImageMenu"
          shouldShow={({ editor: current }) => current.isActive('image')}
          className="z-30 flex items-center gap-0.5 rounded-lg border border-line bg-surface p-1 shadow-pop"
        >
          {imageButton(null, t('imageInline'), 'align-left')}
          {imageButton('left', t('imageLeft'), 'header-column')}
          {imageButton('center', t('imageCenter'), 'align-center')}
          {imageButton('right', t('imageRight'), 'align-right')}
          <Divider />
          <ToolButton label={t('imageOriginal')} onPress={() => run((chain) => chain.updateAttributes('image', { width: null, height: null }))}>
            <Icon name="maximize" />
          </ToolButton>
          <ToolButton label={t('remove')} onPress={() => run((chain) => chain.deleteSelection())}>
            <Icon name="trash" />
          </ToolButton>
        </BubbleMenu>
      )}

      {slash && slash.rect && (
        <div
          role="listbox"
          aria-label={t('insert')}
          className="fixed z-50 max-h-72 w-64 overflow-y-auto rounded-xl border border-line bg-surface p-1 shadow-pop"
          style={{ top: Math.min(slash.rect.bottom + 6, window.innerHeight - 300), left: Math.min(slash.rect.left, window.innerWidth - 270) }}
        >
          {slash.items.map((item, index) => (
            <button
              key={item.id}
              type="button"
              role="option"
              aria-selected={index === slash.index}
              onMouseDown={(event) => {
                event.preventDefault();
                slash.choose(item);
              }}
              className={cx('flex h-9 w-full items-center gap-2.5 rounded-lg px-2 text-left text-[14px]', index === slash.index ? 'bg-surface-2 text-fg' : 'text-fg-2 hover:bg-surface-2')}
            >
              <Icon name={item.icon} />
              {item.label}
            </button>
          ))}
        </div>
      )}

      {dialog && (
        <InsertDialog kind={dialog} editor={editor} embeds={embeds} uploads={node.uploads} onUpload={(files) => void uploadImages(files)} onClose={() => setDialog(null)} />
      )}
    </FieldFrame>
  );
}
