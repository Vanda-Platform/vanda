import { HighlightStyle, syntaxHighlighting, type LanguageSupport } from '@codemirror/language';
import { Compartment, EditorState } from '@codemirror/state';
import { EditorView, placeholder as placeholderExtension } from '@codemirror/view';
import { tags } from '@lezer/highlight';
import { basicSetup, minimalSetup } from 'codemirror';
import { Marked, type Tokens } from 'marked';
import { useEffect, useId, useMemo, useRef, useState } from 'react';
import { t } from '../i18n';
import { cx, invalidClass } from '../ui';
import type { FieldNode } from '../types';
import { FieldFrame, useField } from './fields';

// CodeMirror and every language live in separate chunks: a screen without code fields loads none of it.

const LANGUAGES: Record<string, () => Promise<LanguageSupport>> = {
  json: () => import('@codemirror/lang-json').then((m) => m.json()),
  html: () => import('@codemirror/lang-html').then((m) => m.html()),
  css: () => import('@codemirror/lang-css').then((m) => m.css()),
  javascript: () => import('@codemirror/lang-javascript').then((m) => m.javascript()),
  php: () => import('@codemirror/lang-php').then((m) => m.php()),
  sql: () => import('@codemirror/lang-sql').then((m) => m.sql()),
  markdown: () => import('@codemirror/lang-markdown').then((m) => m.markdown()),
  yaml: () => import('@codemirror/lang-yaml').then((m) => m.yaml()),
};

const highlight = HighlightStyle.define([
  { tag: [tags.keyword, tags.operatorKeyword, tags.modifier, tags.bool, tags.null], color: 'var(--v-code-keyword)' },
  { tag: [tags.string, tags.special(tags.string), tags.regexp], color: 'var(--v-code-string)' },
  { tag: [tags.number, tags.atom], color: 'var(--v-code-number)' },
  { tag: [tags.propertyName, tags.attributeName, tags.definition(tags.variableName), tags.tagName], color: 'var(--v-code-property)' },
  { tag: [tags.comment, tags.meta], color: 'var(--v-code-comment)', fontStyle: 'italic' },
  { tag: tags.heading, fontWeight: '600', color: 'var(--v-fg)' },
  { tag: tags.strong, fontWeight: '600' },
  { tag: tags.emphasis, fontStyle: 'italic' },
  { tag: tags.link, color: 'var(--v-accent-fg)', textDecoration: 'underline' },
]);

const theme = EditorView.theme({
  '&': { backgroundColor: 'var(--v-surface)', color: 'var(--v-fg)', fontSize: '13px' },
  '&.cm-focused': { outline: 'none' },
  '.cm-scroller': { fontFamily: 'var(--font-mono)', lineHeight: '1.6' },
  '.cm-content': { padding: '10px 0', caretColor: 'var(--v-accent)' },
  '.cm-line': { padding: '0 12px' },
  '.cm-gutters': { backgroundColor: 'var(--v-surface-2)', color: 'var(--v-fg-3)', border: 'none', borderRight: '1px solid var(--v-line)' },
  '.cm-activeLine': { backgroundColor: 'color-mix(in oklch, var(--v-surface-2) 60%, transparent)' },
  '.cm-activeLineGutter': { backgroundColor: 'var(--v-surface-3)', color: 'var(--v-fg-2)' },
  '.cm-cursor': { borderLeftColor: 'var(--v-accent)' },
  '&.cm-focused .cm-selectionBackground, .cm-selectionBackground, ::selection': { backgroundColor: 'var(--v-accent-soft) !important' },
  '.cm-placeholder': { color: 'var(--v-fg-3)' },
  '.cm-tooltip': { backgroundColor: 'var(--v-surface)', border: '1px solid var(--v-line)', borderRadius: '8px' },
});

interface EditorProps {
  id: string;
  label: string;
  value: string;
  onChange: (value: string) => void;
  language: string;
  minHeight: number;
  lineNumbers: boolean;
  wrap?: boolean;
  disabled: boolean;
  placeholder?: string;
}

export function CodeEditor({ id, label, value, onChange, language, minHeight, lineNumbers, wrap, disabled, placeholder }: EditorProps) {
  const host = useRef<HTMLDivElement>(null);
  const view = useRef<EditorView | null>(null);
  const slots = useMemo(() => ({ language: new Compartment(), editable: new Compartment() }), []);
  const change = useRef(onChange);
  change.current = onChange;

  useEffect(() => {
    const editor = new EditorView({
      parent: host.current!,
      state: EditorState.create({
        doc: value,
        extensions: [
          lineNumbers ? basicSetup : minimalSetup,
          wrap ? EditorView.lineWrapping : [],
          theme,
          syntaxHighlighting(highlight),
          slots.language.of([]),
          slots.editable.of(EditorView.editable.of(!disabled)),
          placeholder ? placeholderExtension(placeholder) : [],
          EditorView.contentAttributes.of({ id, 'aria-label': label }),
          EditorView.theme({ '.cm-content, .cm-gutter': { minHeight: `${minHeight}px` } }),
          EditorView.updateListener.of((update) => {
            if (update.docChanged) change.current(update.state.doc.toString());
          }),
        ],
      }),
    });
    view.current = editor;
    return () => editor.destroy();
    // The editor is created once; later changes go through the effects below.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  useEffect(() => {
    let cancelled = false;
    const load = LANGUAGES[language];
    if (!load) {
      view.current?.dispatch({ effects: slots.language.reconfigure([]) });
      return;
    }
    void load().then((support) => {
      if (!cancelled) view.current?.dispatch({ effects: slots.language.reconfigure(support) });
    });
    return () => {
      cancelled = true;
    };
  }, [language, slots]);

  useEffect(() => {
    view.current?.dispatch({ effects: slots.editable.reconfigure(EditorView.editable.of(!disabled)) });
  }, [disabled, slots]);

  // A value set by the server (a reaction, a refresh) replaces the text.
  useEffect(() => {
    const editor = view.current;
    if (editor && editor.state.doc.toString() !== value) {
      editor.dispatch({ changes: { from: 0, to: editor.state.doc.length, insert: value } });
    }
  }, [value]);

  return <div ref={host} />;
}

const frame = (error?: string) =>
  cx(
    'overflow-hidden rounded-lg border bg-surface shadow-sm transition focus-within:border-accent focus-within:ring-3 focus-within:ring-ring',
    error ? invalidClass : 'border-control-line',
  );

const LANGUAGE_LABELS: Record<string, string> = { json: 'JSON', html: 'HTML', css: 'CSS', javascript: 'JavaScript', php: 'PHP', sql: 'SQL', markdown: 'Markdown', yaml: 'YAML' };

export function CodeField({ node }: { node: FieldNode & { language?: string; minHeight?: number; lineNumbers?: boolean } }) {
  const id = useId();
  const field = useField(node);
  const language = node.language ?? 'plain';

  return (
    <FieldFrame node={node} inputId={id} required={field.required} error={field.error}>
      <div className={frame(field.error)}>
        <CodeEditor
          id={id}
          label={node.label ?? node.path}
          value={typeof field.value === 'string' ? field.value : ''}
          onChange={field.setValue}
          language={language}
          minHeight={node.minHeight ?? 160}
          lineNumbers={node.lineNumbers ?? true}
          disabled={field.disabled}
          placeholder={node.placeholder}
        />
        {LANGUAGE_LABELS[language] && (
          <div className="flex justify-end border-t border-line bg-surface-2 px-3 py-1 text-[11.5px] font-medium text-fg-3">{LANGUAGE_LABELS[language]}</div>
        )}
      </div>
    </FieldFrame>
  );
}

const escapeHtml = (text: string) => text.replace(/[&<>"']/g, (char) => `&#${char.charCodeAt(0)};`);
const safeHref = (href: string) => /^(https?:|mailto:|tel:|\/|#)/i.test(href.trim()) || !/^[a-z][a-z0-9+.-]*:/i.test(href.trim());

/** Raw HTML is shown as text and unsafe links lose their href, so the preview cannot run scripts. */
const markdownRenderer = new Marked({
  gfm: true,
  breaks: true,
  renderer: {
    html: ({ text }: Tokens.HTML | Tokens.Tag) => escapeHtml(text),
    link({ href, title, tokens }: Tokens.Link) {
      const text = this.parser.parseInline(tokens);
      return safeHref(href) ? `<a href="${escapeHtml(href)}"${title ? ` title="${escapeHtml(title)}"` : ''} target="_blank" rel="noopener noreferrer">${text}</a>` : text;
    },
    image({ href, text }: Tokens.Image) {
      return safeHref(href) ? `<img src="${escapeHtml(href)}" alt="${escapeHtml(text)}">` : escapeHtml(text);
    },
  },
});

export function MarkdownField({ node }: { node: FieldNode & { minHeight?: number } }) {
  const id = useId();
  const field = useField(node);
  const [tab, setTab] = useState<'text' | 'preview'>('text');
  const value = typeof field.value === 'string' ? field.value : '';
  const html = useMemo(() => (tab === 'preview' ? (markdownRenderer.parse(value, { async: false }) as string) : ''), [tab, value]);
  const minHeight = node.minHeight ?? 160;

  return (
    <FieldFrame node={node} inputId={id} required={field.required} error={field.error}>
      <div className={frame(field.error)}>
        <div role="tablist" className="flex gap-4 border-b border-line bg-surface-2 px-3">
          {(['text', 'preview'] as const).map((name) => (
            <button
              key={name}
              type="button"
              role="tab"
              aria-selected={tab === name}
              onClick={() => setTab(name)}
              className={cx('py-2 text-[13px] font-medium text-fg-2 hover:text-fg', tab === name && 'text-fg shadow-[inset_0_-2px_0_var(--v-accent)]')}
            >
              {t(name)}
            </button>
          ))}
        </div>
        <div hidden={tab !== 'text'}>
          <CodeEditor
            id={id}
            label={node.label ?? node.path}
            value={value}
            onChange={field.setValue}
            language="markdown"
            minHeight={minHeight}
            lineNumbers={false}
            wrap
            disabled={field.disabled}
            placeholder={node.placeholder}
          />
        </div>
        {tab === 'preview' && (
          <div className="vanda-prose px-4 py-3" style={{ minHeight }} dangerouslySetInnerHTML={{ __html: html || `<p style="color: var(--v-fg-3)">${escapeHtml(t('empty'))}</p>` }} />
        )}
      </div>
    </FieldFrame>
  );
}
