<?php

declare(strict_types=1);

use Symfony\Component\HttpKernel\Exception\HttpException;
use Vanda\Panel;
use Vanda\Screen\ActionCall;
use Vanda\Screen\ScreenRuntime;
use Vanda\Tests\Fixtures\BoundScreen;

beforeEach(function () {
    BoundScreen::$calls = [];
});

/**
 * Sends an action as the browser would: the page's state, a token and the node that was pressed.
 *
 * @param array<string, mixed> $state
 * @return array{0: int, 1: array<string, mixed>}
 */
function press(string $record, string $action, array $state, ?string $node): array
{
    $runtime = ScreenRuntime::boot(BoundScreen::class, ['record' => $record], '/admin/bound');

    return $runtime->act($action, [], ActionCall::sign(BoundScreen::class, $action, []), [...$runtime->state(), ...$state], $node);
}

it('validates what the pressed button validates, whatever node the browser names', function () {
    $empty = ['quick' => ['name' => '']];

    // The modal's button validates the modal, with or without a node in the request.
    expect(press('other', 'saveQuick', $empty, 'action.saveQuick')[0])->toBe(422)
        ->and(press('other', 'saveQuick', $empty, null)[0])->toBe(422)
        ->and(BoundScreen::$calls)->toBe([]);

    // A node that does not make this call cannot pick another scope for it.
    expect(fn () => press('other', 'saveQuick', $empty, 'action.save'))->toThrow(HttpException::class)
        ->and(fn () => press('other', 'saveQuick', $empty, 'layout.0'))->toThrow(HttpException::class)
        ->and(fn () => press('other', 'saveQuick', $empty, 'nothing'))->toThrow(HttpException::class)
        ->and(BoundScreen::$calls)->toBe([]);

    expect(press('other', 'saveQuick', ['quick' => ['name' => 'Anna']], null)[0])->toBe(200)
        ->and(BoundScreen::$calls)->toBe(['saveQuick']);
});

it('refuses a call whose button this page does not draw for this record', function () {
    // The token is the same on every record: only the page knows the button is hidden here.
    expect(fn () => press('self', 'delete', [], 'action.delete'))->toThrow(HttpException::class)
        ->and(fn () => press('self', 'delete', [], null))->toThrow(HttpException::class)
        ->and(BoundScreen::$calls)->toBe([]);

    expect(press('other', 'delete', [], 'action.delete')[0])->toBe(200)
        ->and(BoundScreen::$calls)->toBe(['delete']);
});

it('makes tests call only what the page shows', function () {
    expect(fn () => Panel::test(BoundScreen::class, ['record' => 'self'])->call('delete'))
        ->toThrow(HttpException::class, 'no button on this page makes this call');

    Panel::test(BoundScreen::class, ['record' => 'other'])->call('delete')->assertToast('Deleted');
    Panel::test(BoundScreen::class)->set('quick.name', '')->call('saveQuick')->assertHasErrors(['quick.name']);
});
