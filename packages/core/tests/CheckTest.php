<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Vanda\Tests\Fixtures\BrokenScreen;
use Vanda\Tests\Fixtures\User;

/** Runs vanda:check --json and returns its exit code and the decoded report. */
function vandaCheck(string $filter = ''): array
{
    $code = Artisan::call('vanda:check', array_filter(['filter' => $filter, '--json' => true]));

    return [$code, json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR)];
}

beforeEach(function () {
    User::query()->create(['name' => 'Anna', 'email' => 'anna@example.com']);
});

it('builds every routed screen and passes when nothing is wrong', function () {
    [$code, $report] = vandaCheck();

    $byUri = array_column($report['screens'], null, 'uri');

    expect($code)->toBe(0)
        ->and($report['ok'])->toBeTrue()
        ->and($report['summary']['problems'] + $report['summary']['error'])->toBe(0)
        ->and($report['summary']['screens'])->toBe(count($report['screens']))
        // A model parameter is built with the first record of its table.
        ->and($byUri['/admin/users/{user}/edit'])->toMatchArray(['status' => 'ok', 'parameters' => ['user' => 1]])
        // A screen behind #[Can] is built too: the check may do everything.
        ->and($byUri['/admin/activity']['status'])->toBe('ok')
        ->and($report)->not->toHaveKey('icons');
});

it('reports the problems of a broken screen with file:line, the icons once and exit code 1', function () {
    Route::middleware('web')->prefix('admin')->group(fn () => Route::screen('broken', BrokenScreen::class));

    [$code, $report] = vandaCheck();
    $broken = collect($report['screens'])->firstWhere('uri', '/admin/broken');

    expect($code)->toBe(1)
        ->and($report['ok'])->toBeFalse()
        ->and($report['summary']['problems'])->toBe(1)
        ->and($broken['status'])->toBe('problems')
        ->and($broken['problems'])->toHaveCount(7)
        ->and($broken['problems'][2])->toMatch('/^input "Email" at \S*BrokenScreen\.php:\d+ is bound to "form\.emial".*Did you mean "form\.email"\?/')
        ->and($report['icons'])->toContain('trash');
});

it('skips a screen it has no record for, and narrows the check to a part of the panel', function () {
    User::query()->delete();

    [$code, $report] = vandaCheck('users');
    $edit = collect($report['screens'])->firstWhere('uri', '/admin/users/{user}/edit');

    expect($code)->toBe(0)
        ->and(collect($report['screens'])->every(static fn (array $screen) => str_contains(strtolower($screen['uri'].' '.$screen['screen']), 'users')))->toBeTrue()
        ->and($edit['status'])->toBe('skipped')
        ->and($edit['reason'])->toContain('there is no Vanda\Tests\Fixtures\User record to build the screen with');
});

it('says where a screen that cannot be built went wrong', function () {
    Route::middleware('web')->prefix('admin')->group(fn () => Route::screen('throws', ThrowingScreen::class));

    [$code, $report] = vandaCheck('throws');

    expect($code)->toBe(1)
        ->and($report['screens'][0])->toMatchArray(['status' => 'error', 'error' => 'The screen could not load its data.'])
        ->and($report['screens'][0]['at'])->toMatch('/CheckTest\.php:\d+$/');
});

final class ThrowingScreen extends Vanda\Screen\Screen
{
    public function query(): array
    {
        throw new RuntimeException('The screen could not load its data.');
    }

    public function layout(): iterable
    {
        return [];
    }
}
