<?php

declare(strict_types=1);

use Vanda\Screen\ScreenRuntime;
use Vanda\Tests\Fixtures\TreeScreen;
use Vanda\Vanda;

beforeEach(fn () => TreeScreen::$moves = []);

it('lists tree rows parents first, siblings in order, orphans at the top level', function () {
    $tree = Vanda::test(TreeScreen::class)->node('layout.0');

    expect(array_map(fn (array $row) => [$row['key'], $row['parent'] ?? null, $row['depth']], $tree['rows']))->toBe([
        ['1', null, 0],
        ['2', null, 0],
        ['4', '2', 1],
        ['3', '2', 1],
        ['5', '3', 2],
        ['6', null, 0],
    ])->and($tree['move'])->toMatchArray(['name' => 'move', 'inputs' => ['id', 'parent', 'position']])
        ->and($tree['expandDepth'])->toBe(1);
});

it('passes the values of a move from the browser to the action', function () {
    Vanda::test(TreeScreen::class)
        ->callWithInput('move', ['id' => 5, 'parent' => null, 'position' => 1])
        ->assertToast('Moved');

    expect(TreeScreen::$moves)->toBe([['id' => 5, 'parent' => null, 'position' => 1]]);
});

it('accepts only the signed input names and scalar values', function () {
    $runtime = ScreenRuntime::boot(TreeScreen::class, [], '/tree');
    $move = Vanda::test(TreeScreen::class)->node('layout.0')['move'];

    [$status] = $runtime->act('move', ['id' => 5, 'parent' => [1], 'position' => 0, 'extra' => 'x'], $move['token'], [], inputs: $move['inputs']);
    expect($status)->toBe(200)
        ->and(TreeScreen::$moves)->toBe([['id' => 5, 'parent' => null, 'position' => 0]]);

    expect(fn () => $runtime->act('move', ['id' => 5], $move['token'], [], inputs: ['id']))
        ->toThrow(Symfony\Component\HttpKernel\Exception\HttpException::class);
});

it('describes a tree select with parents and an excluded branch, keeping the order of the records', function () {
    $select = Vanda::test(TreeScreen::class)->node('parent');

    expect($select['type'])->toBe('treeselect')
        ->and($select['searchable'])->toBeTrue()
        ->and($select['options'])->toBe([
            ['value' => 1, 'label' => 'Home'],
            ['value' => 2, 'label' => 'About'],
            ['value' => 3, 'label' => 'Team', 'parent' => 2, 'disabled' => true],
            ['value' => 5, 'label' => 'People', 'parent' => 3, 'disabled' => true],
            ['value' => 4, 'label' => 'History', 'parent' => 2],
            ['value' => 6, 'label' => 'Orphan'],
        ]);
});

it('drops tree select values that are excluded or unknown', function () {
    Vanda::test(TreeScreen::class)
        ->set('parent', 5)->assertSet('parent', null)
        ->set('parent', '4')->assertSet('parent', 4)
        ->set('sections', [2, '3', 42, 'x'])->assertSet('sections', [2, 3]);
});
