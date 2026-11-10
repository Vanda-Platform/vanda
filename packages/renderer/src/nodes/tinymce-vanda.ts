/**
 * Vanda's look for TinyMCE over its oxide skin: the frame of a field, a toolbar like RichText's,
 * menus, dialogs, fields and buttons of the panel, and inside the editor's frame the text set as
 * RichText sets it. Colours are the panel's tokens (--v-*), so the dark theme and the brand colour
 * follow by themselves.
 *
 * The rules start with `.tox.tox` (or name an element) to win over oxide's `.tox …` ones, which
 * come first in the same <style>.
 */

const FONT = '"Onest", ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif';
const MONO = '"JetBrains Mono", ui-monospace, "SF Mono", Menlo, monospace';

export const VANDA_UI = `
.tox.tox, .tox.tox-tinymce-aux, .tox.tox .tox-dialog__title, .tox.tox .tox-textfield, .tox.tox .tox-textarea,
.tox.tox .tox-listboxfield .tox-listbox--select, .tox.tox .tox-selectfield select, .tox.tox .tox-toolbar-textfield,
.tox.tox .tox-button, .tox.tox .tox-button-link { font-family: ${FONT}; }

/* The frame: a field's edge, the accent and its ring while the editor has the focus, red when invalid. */
.vanda-tinymce .tox.tox-tinymce { border: 1px solid var(--v-control-line); border-radius: 8px; box-shadow: var(--v-shadow-sm); transition: border-color .15s, box-shadow .15s; }
.vanda-tinymce .tox.tox-tinymce:focus-within { border-color: var(--v-accent); box-shadow: 0 0 0 3px var(--v-ring); }
.vanda-tinymce[data-invalid] .tox.tox-tinymce { border-color: var(--v-danger); box-shadow: 0 0 0 3px color-mix(in oklch, var(--v-danger) 16%, transparent); }
.tox.tox .tox-edit-area::before { display: none; }
.tox.tox .tox-edit-area__iframe { background: var(--v-surface); }

/* Menu and toolbar: one band with a line under it, as RichText's toolbar. */
.tox.tox:not(.tox-tinymce-inline) .tox-editor-header,
.tox.tox:not(.tox-tinymce-inline).tox-tinymce--toolbar-sticky-on .tox-editor-header { background: color-mix(in oklch, var(--v-surface-2) 60%, var(--v-surface)); border-bottom: 1px solid var(--v-line); box-shadow: none; padding: 2px 0; }
.tox.tox .tox-menubar, .tox.tox .tox-toolbar-overlord, .tox.tox .tox-toolbar, .tox.tox .tox-toolbar__primary, .tox.tox .tox-toolbar__overflow { background: transparent; }
.tox.tox .tox-menubar { padding: 0 6px; }
.tox.tox .tox-toolbar--scrolling { scrollbar-width: thin; scrollbar-color: var(--v-line-strong) transparent; }
.tox.tox .tox-menubar + .tox-toolbar, .tox.tox .tox-menubar + .tox-toolbar-overlord { border-top: 1px solid var(--v-line); margin-top: 2px; padding: 0; }
.tox.tox .tox-toolbar__group { padding: 0 6px; }
.tox.tox:not([dir=rtl]) .tox-toolbar__group:not(:last-of-type) { border-right: 0; }
.tox.tox .tox-toolbar__group:not(:last-of-type)::after { content: ''; width: 1px; height: 20px; margin-left: 6px; background: var(--v-line); }

.tox.tox .tox-mbtn { height: 28px; margin: 3px 1px; padding: 0 8px; border-radius: 6px; color: var(--v-fg-2); font-size: 13.5px; }
.tox.tox .tox-mbtn:hover:not(:disabled):not(.tox-mbtn--active), .tox.tox .tox-mbtn:focus:not(:disabled) { background: var(--v-surface-3); color: var(--v-fg); }
.tox.tox .tox-mbtn--active, .tox.tox .tox-mbtn--active:hover { background: var(--v-surface-3); color: var(--v-fg); }

.tox.tox .tox-tbtn, .tox.tox .tox-split-button { height: 28px; margin: 4px 1px; border-radius: 6px; color: var(--v-fg-2); }
.tox.tox .tox-tbtn { width: 28px; }
.tox.tox .tox-tbtn--select, .tox.tox .tox-tbtn--bespoke { width: auto; padding: 0 4px 0 8px; font-size: 13.5px; background: transparent; }
.tox.tox .tox-tbtn--bespoke .tox-tbtn__select-label { width: 6.5em; }
.tox.tox .tox-tbtn__select-label { margin: 0 2px 0 0; }
.tox.tox .tox-tbtn.tox-tbtn-more { width: auto; padding: 0 6px; }
.tox.tox .tox-split-button .tox-tbtn { margin: 0; border-radius: 0; }
.tox.tox .tox-split-button__chevron { width: 18px; }
.tox.tox .tox-tbtn:hover, .tox.tox .tox-tbtn:focus, .tox.tox .tox-tbtn:active, .tox.tox .tox-split-button:hover, .tox.tox .tox-split-button:focus,
.tox.tox .tox-split-button .tox-tbtn:hover, .tox.tox .tox-split-button .tox-tbtn:focus { background: var(--v-surface-3); color: var(--v-fg); box-shadow: none; }
.tox.tox .tox-split-button:hover .tox-tbtn:hover { background: color-mix(in oklch, var(--v-fg) 8%, var(--v-surface-3)); }
.tox.tox .tox-tbtn--enabled, .tox.tox .tox-tbtn--enabled:hover { background: var(--v-accent-soft); color: var(--v-accent-fg); }
.tox.tox .tox-tbtn--disabled, .tox.tox .tox-tbtn--disabled:hover, .tox.tox .tox-tbtn:disabled, .tox.tox .tox-tbtn:disabled:hover { background: transparent; color: var(--v-fg-3); opacity: .5; }
.tox.tox .tox-tbtn svg, .tox.tox .tox-mbtn svg, .tox.tox .tox-split-button svg, .tox.tox .tox-collection__item svg, .tox.tox .tox-statusbar svg,
.tox.tox .tox-button--naked svg, .tox.tox .tox-button--icon svg, .tox.tox .tox-notification svg { fill: currentColor !important; }
.tox.tox .tox-tbtn__select-chevron, .tox.tox .tox-split-button__chevron { color: var(--v-fg-3); }

/* Status bar: the path and the word count in small grey type. */
.tox.tox .tox-statusbar { height: 28px; padding: 0 10px; border-top: 1px solid var(--v-line); background: var(--v-surface); color: var(--v-fg-3); font-size: 12px; }
.tox.tox .tox-statusbar a, .tox.tox .tox-statusbar__path-item, .tox.tox .tox-statusbar__wordcount { color: var(--v-fg-3); }
.tox.tox .tox-statusbar a:hover:not(:disabled):not([aria-disabled=true]), .tox.tox .tox-statusbar__path-item:hover:not(:disabled):not([aria-disabled=true]),
.tox.tox .tox-statusbar__wordcount:hover:not(:disabled):not([aria-disabled=true]), .tox.tox .tox-statusbar a:focus:not(:disabled):not([aria-disabled=true]),
.tox.tox .tox-statusbar__path-item:focus:not(:disabled):not([aria-disabled=true]), .tox.tox .tox-statusbar__wordcount:focus:not(:disabled):not([aria-disabled=true]) { color: var(--v-fg); }
.tox.tox .tox-statusbar__resize-handle { color: var(--v-fg-3); }

/* Menus: the panel's popovers. */
.tox.tox .tox-menu { background: var(--v-surface); border: 1px solid var(--v-line); border-radius: 12px; box-shadow: var(--v-shadow-lg); }
.tox.tox .tox-menu.tox-collection.tox-collection--list { padding: 4px; }
.tox.tox .tox-collection--list .tox-collection__group { border-color: var(--v-line); padding: 4px 0; }
.tox.tox .tox-collection__group-heading { background: transparent; color: var(--v-fg-3); font-size: 12px; font-weight: 500; }
.tox.tox .tox-collection__item { border-radius: 6px; color: var(--v-fg); }
.tox.tox .tox-collection--list .tox-collection__item { min-height: 32px; padding: 4px 8px; }
.tox.tox .tox-collection__item-label { font-size: 14px; line-height: 22px; word-break: normal; }
.tox.tox .tox-collection__item-accessory { color: var(--v-fg-3); font-size: 12.5px; }
.tox.tox .tox-collection--list .tox-collection__item-icon, .tox.tox .tox-collection__item-caret { color: var(--v-fg-3); }
.tox.tox .tox-collection--list .tox-collection__item--enabled { background: transparent; color: var(--v-accent-fg); }
.tox.tox .tox-collection--list .tox-collection__item--active, .tox.tox .tox-collection--toolbar .tox-collection__item--active,
.tox.tox .tox-collection--grid .tox-collection__item--active:not(.tox-collection__item--state-disabled) { background: var(--v-surface-2); color: var(--v-fg); }
.tox.tox .tox-collection--toolbar .tox-collection__item { border-radius: 6px; }
.tox.tox .tox-collection--toolbar .tox-collection__item--enabled { background: var(--v-accent-soft); color: var(--v-accent-fg); }
.tox.tox .tox-collection__item--state-disabled { color: var(--v-fg-3); opacity: .6; }

/* Dialogs: the panel's modals. */
.tox.tox .tox-dialog-wrap__backdrop { background-color: var(--v-scrim); }
.tox.tox .tox-dialog { background: var(--v-surface); border: 1px solid var(--v-line); border-radius: 12px; box-shadow: var(--v-shadow-lg); }
.tox.tox .tox-dialog__header { background: var(--v-surface); color: var(--v-fg); padding: 14px 14px 0 20px; }
.tox.tox .tox-dialog__title { font-size: 16px; font-weight: 600; }
.tox.tox .tox-dialog__body { color: var(--v-fg); font-size: 14px; }
.tox.tox .tox-dialog__body-content { padding: 14px 20px; }
.tox.tox .tox-dialog__body-nav { padding: 14px 0 14px 20px; }
.tox.tox .tox-dialog__body-nav-item { color: var(--v-fg-2); font-size: 13.5px; }
.tox.tox .tox-dialog__body-nav-item:focus { background: var(--v-accent-soft); border-radius: 4px; }
.tox.tox .tox-dialog__body-nav-item--active { border-bottom-color: var(--v-accent); color: var(--v-accent-fg); }
.tox.tox .tox-dialog__footer { background: var(--v-surface); border-top: 1px solid var(--v-line); padding: 12px 20px; }
.tox.tox .tox-label, .tox.tox .tox-toolbar-label { color: var(--v-fg-2); font-size: 13px; font-weight: 500; padding-bottom: 6px; }
.tox.tox .tox-form__group { margin-bottom: 12px; }
.tox.tox .tox-form__group--error { color: var(--v-danger-fg); }

.tox.tox .tox-textfield, .tox.tox .tox-textarea, .tox.tox .tox-toolbar-textfield, .tox.tox .tox-listboxfield .tox-listbox--select,
.tox.tox .tox-selectfield select, .tox.tox .tox-textarea-wrap, .tox.tox .tox-custom-editor { background-color: var(--v-surface); border-color: var(--v-control-line); border-radius: 8px; color: var(--v-fg); font-size: 14px; }
.tox.tox .tox-textfield, .tox.tox .tox-listboxfield .tox-listbox--select, .tox.tox .tox-selectfield select { min-height: var(--v-control); }
.tox.tox .tox-textarea-wrap .tox-textarea { background: transparent; }
.tox.tox .tox-textfield:focus, .tox.tox .tox-textarea:focus, .tox.tox .tox-textarea-wrap:focus-within, .tox.tox .tox-custom-editor:focus-within,
.tox.tox .tox-listboxfield .tox-listbox--select:focus, .tox.tox .tox-selectfield select:focus { background-color: var(--v-surface); border-color: var(--v-accent); box-shadow: 0 0 0 3px var(--v-ring); }
.tox.tox .tox-textarea-wrap .tox-textarea:focus { box-shadow: none; }
.tox.tox .tox-checkbox__icons .tox-checkbox-icon__checked svg, .tox.tox .tox-checkbox__icons .tox-checkbox-icon__indeterminate svg { fill: var(--v-accent); }
.tox.tox .tox-checkbox__icons .tox-checkbox-icon__unchecked svg { fill: var(--v-control-line); }
.tox.tox .tox-checkbox__input:focus + .tox-checkbox__icons { box-shadow: inset 0 0 0 1px var(--v-accent), 0 0 0 3px var(--v-ring); }
.tox.tox .tox-dropzone { background: color-mix(in oklch, var(--v-surface-2) 40%, var(--v-surface)); border: 1px dashed var(--v-line-strong); border-radius: 12px; color: var(--v-fg-2); }
.tox.tox .tox-dropzone--dragover { border-color: var(--v-accent); background: var(--v-accent-soft); }

/* Buttons: primary, secondary and ghost of the panel. */
.tox.tox .tox-button { background: var(--v-accent); border: 1px solid var(--v-accent); border-radius: 8px; color: var(--v-on-accent); font-size: 13.5px; font-weight: 500; min-height: var(--v-control); padding: 4px 14px; }
.tox.tox .tox-button:hover:not(:disabled), .tox.tox .tox-button:focus:not(:disabled), .tox.tox .tox-button:active:not(:disabled) { background: var(--v-accent-hover); border-color: var(--v-accent-hover); color: var(--v-on-accent); }
.tox.tox .tox-button[disabled] { background: var(--v-accent); border-color: var(--v-accent); color: var(--v-on-accent); opacity: .5; }
.tox.tox .tox-button::before { border-radius: 8px; box-shadow: 0 0 0 3px var(--v-ring); }
.tox.tox .tox-button--secondary { background: var(--v-surface); border-color: var(--v-line-strong); color: var(--v-fg); box-shadow: var(--v-shadow-sm); }
.tox.tox .tox-button--secondary:hover:not(:disabled), .tox.tox .tox-button--secondary:focus:not(:disabled), .tox.tox .tox-button--secondary:active:not(:disabled),
.tox.tox .tox-button--secondary.tox-button--enabled { background: var(--v-surface-2); border-color: var(--v-line-strong); color: var(--v-fg); }
.tox.tox .tox-button--secondary[disabled] { background: var(--v-surface); border-color: var(--v-line); color: var(--v-fg-3); }
.tox.tox .tox-button--naked, .tox.tox .tox-button--naked[disabled] { background: transparent; border-color: transparent; box-shadow: none; color: var(--v-fg-3); min-height: 0; }
.tox.tox .tox-button--naked:hover:not(:disabled), .tox.tox .tox-button--naked:focus:not(:disabled), .tox.tox .tox-button--naked:active:not(:disabled),
.tox.tox .tox-button--naked.tox-button--icon:hover:not(:disabled) { background: var(--v-surface-2); border-color: transparent; color: var(--v-fg); }
.tox.tox .tox-button--icon, .tox.tox .tox-button.tox-button--icon { padding: 4px; }

/* Tooltips, context toolbars, notices. */
.tox.tox .tox-tooltip__body { background: var(--v-fg); border-radius: 8px; box-shadow: var(--v-shadow-lg); color: var(--v-surface); font-size: 12.5px; padding: 5px 10px; }
.tox.tox .tox-tooltip--up .tox-tooltip__arrow { border-bottom-color: var(--v-fg); }
.tox.tox .tox-tooltip--down .tox-tooltip__arrow { border-top-color: var(--v-fg); }
.tox.tox .tox-tooltip--left .tox-tooltip__arrow { border-right-color: var(--v-fg); }
.tox.tox .tox-tooltip--right .tox-tooltip__arrow { border-left-color: var(--v-fg); }
.tox.tox .tox-pop__dialog { background: var(--v-surface); border: 1px solid var(--v-line); border-radius: 10px; box-shadow: var(--v-shadow-lg); }
.tox.tox .tox-pop.tox-pop--bottom::after { border-top-color: var(--v-surface); }
.tox.tox .tox-pop.tox-pop--bottom::before { border-top-color: var(--v-line); }
.tox.tox .tox-pop.tox-pop--top::after { border-bottom-color: var(--v-surface); }
.tox.tox .tox-pop.tox-pop--top::before { border-bottom-color: var(--v-line); }
.tox.tox .tox-notification { border-radius: 8px; font-size: 13.5px; }
.tox.tox .tox-notification--error { background: var(--v-danger-soft); border-color: color-mix(in oklch, var(--v-danger) 30%, transparent); color: var(--v-danger-fg); }
.tox.tox .tox-notification--warning, .tox.tox .tox-notification--warn { background: var(--v-warning-soft); border-color: color-mix(in oklch, var(--v-warning) 30%, transparent); color: var(--v-warning-fg); }
.tox.tox .tox-notification--success { background: var(--v-success-soft); border-color: color-mix(in oklch, var(--v-success) 30%, transparent); color: var(--v-success-fg); }
.tox.tox .tox-notification--info { background: var(--v-surface-2); border-color: var(--v-line); color: var(--v-fg); }
.tox.tox .tox-notification p, .tox.tox .tox-notification a { color: inherit; }
`;

/** The panel's tokens the text inside the editor's frame uses: the frame is another document, without them. */
const TOKENS = ['--v-fg', '--v-fg-2', '--v-fg-3', '--v-surface', '--v-surface-2', '--v-line', '--v-line-strong', '--v-accent', '--v-accent-fg', '--v-accent-soft'];

/**
 * The CSS of the text inside the editor's frame, after TinyMCE's own: the panel's font, colours and
 * spacing, as RichText shows text (.vanda-prose). Read when the editor starts; a new theme starts it again.
 */
export function vandaContent(dark: boolean): string {
  const style = getComputedStyle(document.documentElement);
  const tokens = TOKENS.map((name) => `${name}: ${style.getPropertyValue(name).trim()};`).join(' ');

  return `
:root { ${tokens} color-scheme: ${dark ? 'dark' : 'light'}; }
html { background: var(--v-surface); }
body { margin: 14px 16px; font-family: ${FONT}; font-size: 14.5px; line-height: 1.65; color: var(--v-fg); background: var(--v-surface); overflow-wrap: anywhere; -webkit-font-smoothing: antialiased; }
body > * { margin-top: 0; margin-bottom: 0; }
body > * + * { margin-top: 0.75em; }
h1, h2, h3, h4, h5, h6 { font-weight: 600; line-height: 1.3; letter-spacing: -0.01em; }
h1 { font-size: 1.6em; } h2 { font-size: 1.35em; } h3 { font-size: 1.15em; } h4 { font-size: 1em; }
body > h2, body > h3, body > h4 { margin-top: 1.1em; }
body > :first-child { margin-top: 0; }
ul, ol { padding-left: 1.4em; }
li + li { margin-top: 0.25em; }
a { color: var(--v-accent-fg); text-decoration: underline; text-underline-offset: 3px; }
strong, b { font-weight: 600; }
.mce-content-body:not([dir=rtl]) blockquote { border-left: 3px solid var(--v-line-strong); margin-left: 0; padding-left: 1em; color: var(--v-fg-2); }
code { font-family: ${MONO}; font-size: 0.88em; background: var(--v-surface-2); border-radius: 5px; padding: 0.1em 0.35em; }
pre { font-family: ${MONO}; font-size: 13px; background: var(--v-surface-2); border-radius: 10px; padding: 0.9em 1em; overflow-x: auto; }
pre code { background: none; padding: 0; }
hr { border: 0; border-top: 1px solid var(--v-line); margin: 1.4em 0; }
img { max-width: 100%; height: auto; }
figure figcaption { color: var(--v-fg-3); font-size: 0.9em; }
table { border-collapse: collapse; }
table:not([cellpadding]) th, table:not([cellpadding]) td { padding: 0.4em 0.6em; }
table[border]:not([border="0"]):not([style*="border-color"]) th, table[border]:not([border="0"]):not([style*="border-color"]) td,
table[border]:not([border="0"]):not([style*="border-color"]) thead, table[border]:not([border="0"]):not([style*="border-color"]) tbody,
table[border]:not([border="0"]):not([style*="border-color"]) tr { border-color: var(--v-line); }
th { background: var(--v-surface-2); font-weight: 600; text-align: left; }
::selection { background: var(--v-accent-soft); }
.mce-content-body [data-mce-selected="inline-boundary"] { background: var(--v-accent-soft); }
.mce-content-body img[data-mce-selected], .mce-content-body table[data-mce-selected], .mce-content-body video[data-mce-selected],
.mce-content-body iframe[data-mce-selected], .mce-content-body hr[data-mce-selected] { outline: 2px solid var(--v-accent); }
.mce-content-body td[data-mce-selected], .mce-content-body th[data-mce-selected] { background: var(--v-accent-soft) !important; }
.mce-content-body td[data-mce-selected]::after, .mce-content-body th[data-mce-selected]::after { background: none; border-color: var(--v-accent); }
.mce-content-body [contentEditable=false][data-mce-selected] { outline: 2px solid var(--v-accent); }
.mce-content-body[data-mce-placeholder]:not(.mce-visualblocks)::before { color: var(--v-fg-3); }
.mce-content-body .mce-item-anchor { background-color: var(--v-accent-soft); }
`;
}
