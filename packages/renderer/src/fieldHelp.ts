import type { FieldNode } from './types';

/**
 * Where a field's hint (Field::help()) shows:
 * - `label`: an «i» at the end of the label row, the text in a tooltip;
 * - `beside`: the same «i» to the right of a field that has no label;
 * - `inline`: as text under the field (->help($text, inline: true));
 * - `column`: nowhere in the field: its label is hidden because a column header names it (a table of
 *   a Repeater), and the «i» is in that header;
 * - `none`: no hint.
 * Wherever it shows, the text stays in the field for screen readers (aria-describedby).
 */
export type HelpPlace = 'none' | 'label' | 'beside' | 'inline' | 'column';

export function helpPlace(node: Pick<FieldNode, 'help' | 'helpInline' | 'label' | 'labelHidden'>): HelpPlace {
  if (!node.help) return 'none';
  if (node.labelHidden) return 'column';
  if (node.helpInline) return 'inline';
  return node.label ? 'label' : 'beside';
}

/** The ids that describe a control: its error first, then its hint. */
export function describedBy(id: string, error: string | undefined, help: string | undefined): string | undefined {
  return [error ? `${id}-error` : null, help ? `${id}-help` : null].filter(Boolean).join(' ') || undefined;
}

/**
 * Hints of a Repeater and its fields. Its own hint goes to its label row (or under the list with
 * `inline`). A field of a table layout puts its «i» in its column header; the single field of a list
 * layout has no header, so its hint joins the Repeater's; a field of the blocks layout shows its own,
 * beside its own label.
 */
export function repeaterHints(
  layout: 'list' | 'table' | 'blocks',
  node: Pick<FieldNode, 'help' | 'helpInline'>,
  templates: Pick<FieldNode, 'path' | 'help' | 'helpInline'>[],
): { label: string[]; columns: Record<string, string>; inline: string[] } {
  const label: string[] = [];
  const columns: Record<string, string> = {};
  const inline: string[] = [];

  if (node.help) (node.helpInline ? inline : label).push(node.help);
  if (layout !== 'blocks') {
    for (const template of templates) {
      if (!template.help) continue;
      if (template.helpInline) inline.push(template.help);
      else if (layout === 'table') columns[template.path] = template.help;
      else label.push(template.help);
    }
  }

  return { label: [...new Set(label)], columns, inline: [...new Set(inline)] };
}
