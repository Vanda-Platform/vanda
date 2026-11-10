import tinymce from './tinymce-core';
import type { Editor, RawEditorOptions } from 'tinymce';
import 'tinymce/models/dom';
import 'tinymce/themes/silver';
import 'tinymce/icons/default';
import './tinymce-plugins';
import emojisUrl from 'tinymce/plugins/emoticons/js/emojis.min.js?url';
import { useEffect, useId, useRef, useState } from 'react';
import { lang, t } from '../i18n';
import { useStore } from '../store';
import { upload } from '../transport';
import type { FieldNode } from '../types';
import { FieldFrame, useField } from './fields';
import { formatSize } from './upload';
import { vandaContent, VANDA_UI } from './tinymce-vanda';
import { toWebp } from '../webp';

// TinyMCE 6 lives in its own chunk, loaded only by screens with a TinyMce field. Nothing is fetched
// from a CDN: the editor, its plugins, icons, skins and the Russian pack are in the bundle; the skin
// of the current theme, the language and the emoji load as separate files of it when needed.

type LinkItem = { title: string; value?: string; menu?: LinkItem[] };

type TinyMceNode = FieldNode & {
  height?: number;
  plugins?: string[];
  /** TinyMCE's toolbar words ("bold italic | link"), a list for several rows, "" for none. */
  toolbar?: string | string[];
  /** Missing: TinyMCE's menu; "" none; else the menus to show ("edit insert format"). */
  menubar?: string;
  linkList?: LinkItem[];
  /** Bytes per image uploaded into the text (TinyMce::uploads()); missing when off. */
  uploads?: number | null;
  /** WebP quality pictures go up at (TinyMce::uploads(webp: …)); missing: as they are. */
  webp?: number | null;
};

/**
 * Links keep every attribute TinyMCE knows for them and any data-* or aria-* one: the site may tie a
 * link to a record by it (<a data-product-id="42">). The leading "-" keeps an empty link out, as
 * TinyMCE's own rule for <a> does.
 */
const LINK_ELEMENT =
  '-a[href|target|rel|media|hreflang|type|charset|name|rev|shape|coords|download|referrerpolicy' +
  '|id|accesskey|class|dir|lang|style|tabindex|title|role|contenteditable|draggable|hidden|spellcheck|translate|data-*|aria-*]';

type Skin = { ui: string; content: string };

/** The panel's font stylesheets (<link data-vanda-fonts> in the layout), for the editor's frame; false when there are none. */
function fontStylesheets(): string[] | false {
  const links = [...document.querySelectorAll<HTMLLinkElement>('link[rel="stylesheet"][data-vanda-fonts]')].map((link) => link.href);
  return links.length > 0 ? links : false;
}

const skins: Partial<Record<'light' | 'dark', Promise<Skin>>> = {};

/** TinyMCE's skin for the theme, from its own file of the bundle, once. */
function loadSkin(dark: boolean): Promise<Skin> {
  const name = dark ? 'dark' : 'light';
  skins[name] ??= (dark ? import('./tinymce-skin-dark') : import('./tinymce-skin-light')).then((module) => module.default);
  return skins[name];
}

/** The skin of TinyMCE's toolbars, menus and dialogs: one for every editor on the page, light or dark. */
function applySkin(skin: Skin) {
  let style = document.getElementById('vanda-tinymce-skin') as HTMLStyleElement | null;
  if (!style) {
    style = document.createElement('style');
    style.id = 'vanda-tinymce-skin';
    document.head.append(style);
  }
  const css = skin.ui + VANDA_UI;
  if (style.textContent !== css) style.textContent = css;
}

/**
 * TinyMCE puts its menus and dialogs at the end of <body>. Inside a Vanda modal React Aria would make
 * them inert (and pull the focus back into the modal); marked as a top layer they stay usable.
 */
function liftSinks() {
  for (const sink of document.querySelectorAll<HTMLElement>('.tox-tinymce-aux:not([data-react-aria-top-layer])')) {
    sink.setAttribute('data-react-aria-top-layer', 'true');
    sink.inert = false;
    sink.removeAttribute('aria-hidden');
  }
}

if (typeof document !== 'undefined') new MutationObserver(liftSinks).observe(document.body, { childList: true });

let russian: Promise<unknown> | null = null;

/** The Russian pack (tinymce-i18n, langs6: TinyMCE's own translations, MIT as TinyMCE 6), once. */
function loadLanguage(): Promise<unknown> {
  if (lang() !== 'ru') return Promise.resolve();
  russian ??= import('./tinymce-lang-ru').then(() =>
    // A few words the pack leaves in English or lacks.
    tinymce.addI18n('ru', { Redo: 'Повторить', Undo: 'Отменить', 'Press {0} for help': 'Справка: {0}' }),
  );
  return russian;
}

/**
 * Whether `html` is the text the editor holds, written differently: the server's cleaned copy of it
 * after a save, say. It is read in an inert document (no scripts run, no pictures load) and written
 * out by the editor's own serializer, as getContent() would.
 */
function holds(editor: Editor, html: string): boolean {
  const probe = document.implementation.createHTMLDocument('').createElement('div');
  probe.innerHTML = html;
  return editor.serializer.serialize(probe, { getInner: true, format: 'html' }) === editor.getContent();
}

/** Follows the panel's theme (data-theme on <html>), which the user may switch while the editor is open. */
function useDarkTheme(): boolean {
  const [dark, setDark] = useState(() => document.documentElement.dataset.theme === 'dark');
  useEffect(() => {
    const observer = new MutationObserver(() => setDark(document.documentElement.dataset.theme === 'dark'));
    observer.observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });
    return () => observer.disconnect();
  }, []);
  return dark;
}

export function TinyMceField({ node }: { node: TinyMceNode }) {
  const id = `vanda-tinymce-${useId().replace(/[^\w-]/g, '')}`;
  const store = useStore();
  const field = useField(node);
  const value = typeof field.value === 'string' ? field.value : '';
  const dark = useDarkTheme();
  const textarea = useRef<HTMLTextAreaElement>(null);
  const [editor, setEditor] = useState<Editor | null>(null);
  // The value the store has as far as this editor knows; anything else in the store came from outside.
  const known = useRef(value);
  // The text the editor was loaded with and what TinyMCE made of it: that output means "not changed".
  const loaded = useRef(value);
  const normalized = useRef<string | null>(null);
  // Handlers TinyMCE (created once per configuration) calls: they always see the latest render.
  const latest = useRef({ node, field, value });
  latest.current = { node, field, value };

  // What needs a new editor when it changes; the text and the disabled state do not.
  const config = JSON.stringify([node.height, node.plugins, node.toolbar, node.menubar, node.linkList, Boolean(node.uploads), node.placeholder, node.label, dark]);

  useEffect(() => {
    const target = textarea.current;
    if (!target) return;
    let alive = true;
    let created: Editor | null = null;
    let pending = false;
    // The text the editor starts with: the store's value at the moment TinyMCE is created.
    let initial = '';

    const sync = () => {
      pending = false;
      if (!alive || !created) return;
      const html = created.getContent();
      const next = html === normalized.current ? loaded.current : html;
      if (next === known.current) return;
      known.current = next;
      latest.current.field.setValue(next);
    };
    // Typing fires several events at once: the text is read once for them.
    const schedule = () => {
      if (pending) return;
      pending = true;
      queueMicrotask(sync);
    };

    const uploadImage: NonNullable<RawEditorOptions['images_upload_handler']> = async (blobInfo, progress) => {
      const { node: current } = latest.current;
      const blob = blobInfo.blob();
      const original = new File([blob], blobInfo.filename(), { type: blob.type });
      // JPEG and PNG go into the text as WebP (TinyMce::uploads(webp: …)); the size counts after that.
      const file = current.webp ? await toWebp(original, current.webp) : original;
      if (current.uploads && file.size > current.uploads) throw { message: t('imageTooLarge', { max: formatSize(current.uploads) }), remove: true };
      try {
        const { status, data } = await upload(store.get().page, current.path, file, (fraction) => progress(Math.round(fraction * 100))).promise;
        if (status === 200) return String(data.value);
        throw new Error(data.errors?.[current.path]?.[0] ?? t('uploadFailed'));
      } catch (error) {
        throw { message: error instanceof Error ? error.message : t('uploadFailed'), remove: true };
      }
    };

    const options: RawEditorOptions = {
      target,
      height: node.height ?? 400,
      plugins: node.plugins ?? [],
      toolbar: node.toolbar === '' ? false : (node.toolbar ?? false),
      menubar: node.menubar === undefined ? true : node.menubar === '' ? false : node.menubar,
      language: lang() === 'ru' ? 'ru' : undefined,
      // The skin is ours (applySkin), the text's CSS goes inside the editor's frame (content_style below),
      // with the panel's fonts: the frame is a document of its own and loads them again (from the cache).
      skin: false,
      content_css: fontStylesheets(),
      // Nothing should load from there (every part is in the bundle); a part that did would come from Vanda's files.
      base_url: import.meta.url.replace(/\/[^/]*$/, ''),
      suffix: '.min',
      promotion: false,
      branding: false,
      browser_spellcheck: true,
      // Addresses stay as written: "/catalog/x" must not turn into a path relative to the admin page.
      convert_urls: false,
      relative_urls: false,
      // «Кавычки» stay as typed, not &laquo;…&raquo;: only & < > " are written as entities.
      entity_encoding: 'raw',
      extended_valid_elements: LINK_ELEMENT,
      link_list: node.linkList,
      images_upload_handler: node.uploads ? uploadImage : undefined,
      // Without uploads a pasted picture would land in the text as a huge data: address.
      paste_data_images: Boolean(node.uploads),
      emoticons_database_url: emojisUrl,
      // Vanda asks before leaving a page with unsaved changes; drafts stay per page and field.
      autosave_ask_before_unload: false,
      autosave_prefix: `vanda-tinymce-${window.location.pathname}-${node.path}-`,
      placeholder: node.placeholder,
      readonly: latest.current.field.disabled,
      iframe_aria_text: node.label ?? node.path,
      setup: (instance) => {
        instance.on('init', () => {
          loaded.current = initial;
          known.current = initial;
          normalized.current = instance.getContent();
          liftSinks();
        });
        instance.on('input change undo redo ExecCommand SetContent', schedule);
        // Keys typed in the editor's frame never reach the page: Cmd/Ctrl+S and the like go on to the panel's hotkeys.
        instance.on('keydown', (event: KeyboardEvent) => {
          if (!(event.metaKey || event.ctrlKey) || event.defaultPrevented || event.key.length !== 1) return;
          sync();
          const forwarded = new KeyboardEvent('keydown', { key: event.key, code: event.code, metaKey: event.metaKey, ctrlKey: event.ctrlKey, shiftKey: event.shiftKey, altKey: event.altKey, cancelable: true });
          window.dispatchEvent(forwarded);
          if (forwarded.defaultPrevented) event.preventDefault();
        });
      },
    };

    void Promise.all([loadSkin(dark), loadLanguage()])
      .then(([skin]) => {
        if (!alive) return [];
        applySkin(skin);
        initial = latest.current.value;
        target.value = initial;
        // TinyMCE checks every option it is given: one left undefined is left out.
        const given = Object.fromEntries(Object.entries({ ...options, content_style: skin.content + vandaContent(dark) }).filter(([, option]) => option !== undefined));
        return tinymce.init(given as RawEditorOptions);
      })
      .then((editors) => {
        const instance = editors[0] ?? null;
        if (!alive) {
          instance?.remove();
          return;
        }
        created = instance;
        setEditor(instance);
      });

    return () => {
      alive = false;
      setEditor(null);
      created?.remove();
    };
    // The editor is rebuilt only when its configuration changes (`config`); handlers read `latest`.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [config]);

  // Text set from outside (a reaction, a refresh, a moved Repeater row, «Вернуть») replaces the content;
  // the same text cleaned by the server after a save keeps the caret and the undo history.
  useEffect(() => {
    if (value === known.current) return;
    known.current = value;
    loaded.current = value;
    if (!editor) return;
    if (holds(editor, value)) {
      normalized.current = editor.getContent();
      return;
    }
    editor.setContent(value);
    editor.undoManager.clear();
    editor.undoManager.add();
    editor.setDirty(false);
    normalized.current = editor.getContent();
  }, [editor, value]);

  useEffect(() => {
    editor?.mode.set(field.disabled ? 'readonly' : 'design');
  }, [editor, field.disabled]);

  // The label focuses the text, as it would an input.
  useEffect(() => {
    const label = document.querySelector<HTMLLabelElement>(`label[for="${id}"]`);
    if (!label || !editor) return;
    const focus = () => editor.focus();
    label.addEventListener('click', focus);
    return () => label.removeEventListener('click', focus);
  }, [editor, id]);

  return (
    <FieldFrame node={node} inputId={id} required={field.required} error={field.error}>
      {/* While TinyMCE starts, a box of its height covers it: the page does not jump. */}
      <div className="vanda-tinymce relative min-w-0" data-invalid={field.error ? '' : undefined} style={editor ? undefined : { minHeight: node.height ?? 400 }}>
        {!editor && <div aria-busy="true" className="absolute inset-0 z-10 animate-pulse rounded-lg bg-surface-2 motion-reduce:animate-none" />}
        {/* TinyMCE hides the textarea and puts the editor after it, inside this wrapper React leaves alone. */}
        <div>
          <textarea ref={textarea} id={id} defaultValue={value} hidden aria-invalid={field.error ? true : undefined} />
        </div>
      </div>
    </FieldFrame>
  );
}
