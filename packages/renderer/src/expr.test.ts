import { describe, expect, it } from 'vitest';
import fixtures from '../../schema/fixtures/expressions.json';
import { evaluate } from './expr';
import type { ExprNode, State } from './types';

describe('expressions match the PHP evaluator', () => {
  for (const fixture of fixtures as { name: string; expr: ExprNode; state: State; expected: unknown }[]) {
    it(fixture.name, () => {
      expect(evaluate(fixture.expr, fixture.state)).toEqual(fixture.expected);
    });
  }
});
