import { Extension, mergeAttributes, Node as TiptapNode, type Editor, type Range } from '@tiptap/core';
import Image from '@tiptap/extension-image';
import { Plugin, PluginKey, TextSelection } from '@tiptap/pm/state';
import type { Node as PmNode } from '@tiptap/pm/model';
import { Decoration, DecorationSet } from '@tiptap/pm/view';
import Suggestion from '@tiptap/suggestion';

// Extensions of Vanda's rich text editor. They produce only markup Vanda\Support\Html keeps.

/** <iframe> players (RuTube, YouTube…) from the hosts the field allows. */
export const Iframe = TiptapNode.create({
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

export type ImageAlign = 'left' | 'center' | 'right';

const ALIGN_STYLE: Record<ImageAlign, string> = {
  left: 'float: left; margin-right: 1em',
  right: 'float: right; margin-left: 1em',
  center: 'display: block; margin-left: auto; margin-right: auto',
};

/** Pictures with handles to resize them and an alignment kept as allowed inline styles. */
export const VandaImage = Image.extend({
  addAttributes() {
    return {
      ...this.parent?.(),
      align: {
        default: null,
        parseHTML: (element: HTMLElement) => {
          const style = element.getAttribute('style') ?? '';
          if (/float:\s*left/i.test(style)) return 'left';
          if (/float:\s*right/i.test(style)) return 'right';
          if (/margin-left:\s*auto/i.test(style)) return 'center';
          return null;
        },
        renderHTML: (attributes: { align?: ImageAlign | null }) => (attributes.align ? { style: ALIGN_STYLE[attributes.align] } : {}),
      },
    };
  },
}).configure({ resize: { enabled: true, directions: ['top-left', 'top-right', 'bottom-left', 'bottom-right'], alwaysPreserveAspectRatio: true, minWidth: 48, minHeight: 48 } });

// --- Find and replace -------------------------------------------------------------------------

interface SearchState {
  term: string;
  results: Range[];
  index: number;
  decorations: DecorationSet;
}

export const searchKey = new PluginKey<SearchState>('vandaSearch');

/** Matches of the term inside each block, also across bold or linked parts of a word. */
function findMatches(doc: PmNode, term: string): Range[] {
  if (term === '') return [];
  const needle = term.toLocaleLowerCase();
  const results: Range[] = [];

  doc.descendants((node, pos) => {
    if (!node.isTextblock) return true;
    let text = '';
    const positions: number[] = [];
    node.forEach((child, offset) => {
      if (child.isText && child.text) {
        for (let i = 0; i < child.text.length; i++) positions.push(pos + 1 + offset + i);
        text += child.text;
      } else {
        // A picture or a line break inside the block: no match runs across it.
        positions.push(-1);
        text += '\u0000';
      }
    });
    const haystack = text.toLocaleLowerCase();
    for (let at = haystack.indexOf(needle); at !== -1; at = haystack.indexOf(needle, at + needle.length)) {
      const span = positions.slice(at, at + needle.length);
      if (!span.includes(-1)) results.push({ from: span[0]!, to: span[span.length - 1]! + 1 });
    }
    return false;
  });

  return results;
}

const decorate = (doc: PmNode, results: Range[], index: number) =>
  DecorationSet.create(
    doc,
    results.map((range, i) => Decoration.inline(range.from, range.to, { class: i === index ? 'vanda-match vanda-match-current' : 'vanda-match' })),
  );

export const SearchReplace = Extension.create({
  name: 'vandaSearch',
  addProseMirrorPlugins() {
    return [
      new Plugin<SearchState>({
        key: searchKey,
        state: {
          init: () => ({ term: '', results: [], index: 0, decorations: DecorationSet.empty }),
          apply(tr, value) {
            const meta = tr.getMeta(searchKey) as Partial<Pick<SearchState, 'term' | 'index'>> | undefined;
            if (!meta && !tr.docChanged) return value;
            const term = meta?.term ?? value.term;
            const results = findMatches(tr.doc, term);
            const index = results.length === 0 ? 0 : Math.min(meta?.index ?? value.index, results.length - 1);
            return { term, results, index, decorations: decorate(tr.doc, results, index) };
          },
        },
        props: {
          decorations: (state) => searchKey.getState(state)?.decorations ?? DecorationSet.empty,
        },
      }),
    ];
  },
});

export const searchState = (editor: Editor) => searchKey.getState(editor.state) ?? { term: '', results: [], index: 0, decorations: DecorationSet.empty };

export function setSearchTerm(editor: Editor, term: string) {
  editor.view.dispatch(editor.state.tr.setMeta(searchKey, { term, index: 0 }));
}

/** Selects the next (step 1) or previous (-1) match and scrolls to it. */
export function goToMatch(editor: Editor, step: number) {
  const { results, index } = searchState(editor);
  if (results.length === 0) return;
  const next = (index + step + results.length) % results.length;
  const range = results[next]!;
  editor.view.dispatch(editor.state.tr.setMeta(searchKey, { index: next }).setSelection(TextSelection.create(editor.state.doc, range.from, range.to)).scrollIntoView());
}

export function replaceMatch(editor: Editor, replacement: string) {
  const { results, index } = searchState(editor);
  const range = results[index];
  if (!range) return;
  editor.view.dispatch(editor.state.tr.insertText(replacement, range.from, range.to).setMeta(searchKey, { index }));
}

export function replaceAll(editor: Editor, replacement: string) {
  const { results } = searchState(editor);
  if (results.length === 0) return;
  const tr = editor.state.tr;
  for (const range of [...results].reverse()) tr.insertText(replacement, range.from, range.to);
  editor.view.dispatch(tr.setMeta(searchKey, { index: 0 }));
}

// --- "/" commands -----------------------------------------------------------------------------

export interface SlashItem {
  id: string;
  label: string;
  icon: string;
  /** Words it is also found by, e.g. "h2 заголовок heading". */
  keywords: string;
  run: (editor: Editor) => void;
}

export interface SlashView {
  items: SlashItem[];
  index: number;
  rect: DOMRect | null;
  choose: (item: SlashItem) => void;
}

/**
 * Typing "/" at the start of a word opens a list of blocks to insert. The list is drawn by the
 * React field (onChange gets what to show); the keys are handled here.
 */
export function SlashCommand(items: () => SlashItem[], onChange: (view: SlashView | null) => void) {
  return Extension.create({
    name: 'vandaSlash',
    addProseMirrorPlugins() {
      return [
        Suggestion<SlashItem, SlashItem>({
          editor: this.editor,
          char: '/',
          pluginKey: new PluginKey('vandaSlash'),
          allow: ({ state }) => !state.selection.$from.parent.type.spec.code,
          items: ({ query }) => {
            const words = query.toLocaleLowerCase();
            return items().filter((item) => `${item.label} ${item.keywords}`.toLocaleLowerCase().includes(words));
          },
          command: ({ editor, range, props }) => {
            editor.chain().focus().deleteRange(range).run();
            props.run(editor);
          },
          render: () => {
            let props: { items: SlashItem[]; command: (item: SlashItem) => void; clientRect?: (() => DOMRect | null) | null } | null = null;
            let index = 0;
            const show = () =>
              onChange(props && props.items.length > 0 ? { items: props.items, index, rect: props.clientRect?.() ?? null, choose: (item) => props?.command(item) } : null);

            return {
              onStart: (next) => {
                props = next;
                index = 0;
                show();
              },
              onUpdate: (next) => {
                props = next;
                index = 0;
                show();
              },
              onKeyDown: ({ event }) => {
                if (!props || props.items.length === 0) return false;
                if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                  index = (index + (event.key === 'ArrowDown' ? 1 : -1) + props.items.length) % props.items.length;
                  show();
                  return true;
                }
                if (event.key === 'Enter' || event.key === 'Tab') {
                  props.command(props.items[index]!);
                  return true;
                }
                if (event.key === 'Escape') {
                  props = null;
                  show();
                  return true;
                }
                return false;
              },
              onExit: () => {
                props = null;
                onChange(null);
              },
            };
          },
        }),
      ];
    },
  });
}

// --- Paste from Word and Google Docs ------------------------------------------------------------

/** Styles a paste may bring in; the rest (fonts, sizes in pt, margins, mso-*) goes. */
const PASTE_STYLES = ['text-align', 'font-weight', 'font-style', 'text-decoration', 'color', 'background-color', 'list-style-type'];

/** The colour every Word and Google Docs span carries: dropping it lets the text follow the theme. */
const PLAIN_COLOR = /^(#000(000)?|black|rgb\(\s*0\s*,\s*0\s*,\s*0\s*\)|windowtext|transparent|inherit|initial|#fff(fff)?|white|rgb\(\s*255\s*,\s*255\s*,\s*255\s*\))$/i;

function unwrap(element: Element) {
  element.replaceWith(...element.childNodes);
}

/**
 * Pasted HTML reduced to what the editor and the server keep: no Office tags (o:p, w:*),
 * classes, fonts or mso-* styles; Google Docs' bold wrapper and plain black text undone.
 */
export function cleanPastedHtml(html: string, elements: Record<string, string[]>): string {
  const doc = new DOMParser().parseFromString(html, 'text/html');
  doc.querySelectorAll('style, meta, link, script, title, xml').forEach((element) => element.remove());

  const comments = doc.createTreeWalker(doc.body, NodeFilter.SHOW_COMMENT);
  const dead: Node[] = [];
  while (comments.nextNode()) dead.push(comments.currentNode);
  dead.forEach((node) => node.parentNode?.removeChild(node));

  // Deepest first, so unwrapping never skips a child.
  for (const element of [...doc.body.querySelectorAll('*')].reverse()) {
    const tag = element.tagName.toLowerCase();
    if (tag.includes(':') || tag === 'font') {
      unwrap(element);
      continue;
    }

    const original = element.getAttribute('style') ?? '';
    const kept: string[] = [];
    for (const declaration of original.split(';')) {
      const [property = '', ...rest] = declaration.split(':');
      const name = property.trim().toLowerCase();
      const value = rest.join(':').trim();
      if (!PASTE_STYLES.includes(name) || value === '') continue;
      if ((name === 'color' || name === 'background-color') && PLAIN_COLOR.test(value)) continue;
      if (name === 'font-weight' && /^(normal|400)$/i.test(value)) continue;
      kept.push(`${name}: ${value}`);
    }

    // Bold, italic, underline and strike set by a style become tags, so no bare <span> is left behind.
    if (tag === 'span') {
      const wrap = (name: string, test: (rule: string) => boolean) => {
        const index = kept.findIndex(test);
        if (index === -1) return;
        kept.splice(index, 1);
        const mark = doc.createElement(name);
        mark.append(...element.childNodes);
        element.append(mark);
      };
      wrap('strong', (rule) => /^font-weight: (bold|[6-9]00)$/i.test(rule));
      wrap('em', (rule) => /^font-style: italic$/i.test(rule));
      wrap('u', (rule) => /^text-decoration: underline$/i.test(rule));
      wrap('s', (rule) => /^text-decoration: line-through$/i.test(rule));
    }

    const allowed = elements[tag] ?? [];
    for (const attribute of [...element.attributes]) {
      if (attribute.name !== 'style' && !allowed.includes(attribute.name.toLowerCase())) element.removeAttribute(attribute.name);
    }
    if (kept.length > 0) element.setAttribute('style', kept.join('; '));
    else element.removeAttribute('style');

    // Google Docs wraps everything in <b style="font-weight:normal">; spans without a style are noise.
    if ((tag === 'b' || tag === 'strong') && /font-weight:\s*(normal|400)/i.test(original)) unwrap(element);
    else if (tag === 'span' && element.attributes.length === 0) unwrap(element);
  }

  return doc.body.innerHTML;
}
