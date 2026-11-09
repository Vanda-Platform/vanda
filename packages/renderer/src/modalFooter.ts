import type { UiNode } from './types';

/** Variants of a button that destroys or loses something (Button::danger()). */
const DANGEROUS = new Set(['danger', 'danger-soft']);

/**
 * The buttons of a modal's footer in two groups: the dangerous ones («Удалить») go to the left
 * edge, apart from «Отмена» and the rest on the right, so a hand reaching for «Сохранить» does not
 * land on them. Each group keeps the order of ->buttons(); the left group comes first in the DOM
 * too, so Tab walks the footer in the order it is seen.
 *
 *     modalFooter([save, remove]) // { left: [remove], right: [save] }
 */
export function modalFooter(buttons: UiNode[]): { left: UiNode[]; right: UiNode[] } {
  const dangerous = (button: UiNode) => typeof button.variant === 'string' && DANGEROUS.has(button.variant);
  return { left: buttons.filter(dangerous), right: buttons.filter((button) => !dangerous(button)) };
}
