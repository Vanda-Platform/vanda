import { describe, expect, it } from 'vitest';
import { isFormLayout } from './formLayout';
import type { UiNode } from './types';

const node = (type: string, children: UiNode[] = []): UiNode => ({ type, id: `${type}-${Math.random()}`, children });

describe('isFormLayout', () => {
  it('keeps a page of sections narrow, with alerts, modals and tabs of sections', () => {
    expect(isFormLayout([node('section', [node('grid', [node('input'), node('select')])]), node('section', [node('activity')])])).toBe(true);
    expect(isFormLayout([node('alert'), node('tabs', [node('tab', [node('section')]), node('tab', [node('section')])]), node('modal', [node('table')])])).toBe(true);
  });

  it('gives the full width to anything with rows of data, charts or numbers', () => {
    expect(isFormLayout([node('table')])).toBe(false);
    expect(isFormLayout([node('section', [node('table')])])).toBe(false);
    expect(isFormLayout([node('section'), node('grid', [node('chart'), node('chart')])])).toBe(false);
    expect(isFormLayout([node('tabs', [node('tab', [node('section')]), node('tab', [node('card', [node('table')])])])])).toBe(false);
    expect(isFormLayout([node('metrics'), node('section')])).toBe(false);
  });

  it('needs at least one section: a page of loose fields or alerts stays as it is', () => {
    expect(isFormLayout([])).toBe(false);
    expect(isFormLayout([node('alert')])).toBe(false);
    expect(isFormLayout([node('input'), node('section')])).toBe(false);
  });
});
