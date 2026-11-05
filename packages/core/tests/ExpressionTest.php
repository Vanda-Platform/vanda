<?php

declare(strict_types=1);

use Vanda\Schema\Expr;

$fixtures = json_decode(
    (string) file_get_contents(__DIR__.'/../../schema/fixtures/expressions.json'),
    true,
    flags: JSON_THROW_ON_ERROR,
);

it('evaluates shared fixtures exactly like the renderer', function (array $expr, array $state, mixed $expected) {
    expect(Expr::fromArray($expr)->evaluate($state))->toBe($expected);
})->with(array_combine(
    array_column($fixtures, 'name'),
    array_map(static fn (array $case) => [$case['expr'], $case['state'], $case['expected']], $fixtures),
));

it('serializes the builder to the shared JSON format', function () {
    $expr = Expr::field('form.country')->in(['DE', 'FR'])->and(Expr::field('form.vat')->isEmpty()->not());

    expect($expr->toArray())->toBe([
        'op' => 'and',
        'args' => [
            ['op' => 'in', 'args' => [['field' => 'form.country'], ['value' => ['DE', 'FR']]]],
            ['op' => 'not', 'args' => [['op' => 'empty', 'args' => [['field' => 'form.vat']]]]],
        ],
    ])->and($expr->fields())->toBe(['form.country', 'form.vat']);
});
