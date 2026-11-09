import { describe, expect, it } from 'vitest';
import { modalFooter } from './modalFooter';
import type { UiNode } from './types';

const button = (id: string, variant?: string): UiNode => ({ type: 'button', id, variant });

describe('modalFooter', () => {
  it('puts dangerous buttons on the left and keeps the order of the rest on the right', () => {
    const footer = modalFooter([button('save', 'primary'), button('delete', 'danger'), button('archive', 'secondary'), button('purge', 'danger-soft')]);

    expect(footer.left.map((node) => node.id)).toEqual(['delete', 'purge']);
    expect(footer.right.map((node) => node.id)).toEqual(['save', 'archive']);
  });

  it('leaves a footer without dangerous buttons on the right', () => {
    expect(modalFooter([button('save', 'primary'), { type: 'link', id: 'docs' }])).toEqual({ left: [], right: [button('save', 'primary'), { type: 'link', id: 'docs' }] });
  });
});
