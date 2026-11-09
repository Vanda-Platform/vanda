import type { UiNode } from './types';

/** What needs the page's full width: rows of data, files, charts, numbers side by side. */
const WIDE = new Set(['card', 'table', 'treetable', 'files', 'metrics', 'chart']);

const isWide = (node: UiNode): boolean => WIDE.has(node.type) || (node.children ?? []).some(isWide);

/**
 * A page of forms: only sections, alerts and tabs of them, nothing that needs the full width.
 * Such a page (its header included) stays within 1120px, so a field is never stretched across
 * a wide screen and the eye does not travel from a label on the left to a field far on the right.
 *
 *     isFormLayout(page.layout) // sections only: true; a table anywhere: false
 */
export function isFormLayout(nodes: UiNode[]): boolean {
  const content = nodes.filter((node) => node.type !== 'modal');
  return (
    content.some((node) => node.type === 'section' || node.type === 'tabs') &&
    content.every((node) => (node.type === 'tabs' ? isFormLayout((node.children ?? []).flatMap((tab) => tab.children ?? [])) : ['section', 'alert', 'empty'].includes(node.type) && !isWide(node)))
  );
}
