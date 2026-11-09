<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Vanda\Layouts\Layout;
use Vanda\Support\Preferences;
use Vanda\Tests\Fixtures\TableScreen;
use Vanda\Tests\Fixtures\User;

beforeEach(function () {
    $this->anna = User::query()->create(['name' => 'Anna', 'email' => 'anna@example.com', 'country' => 'DE']);
    $this->boris = User::query()->create(['name' => 'Boris', 'email' => 'boris@test.dev', 'country' => 'FR']);
});

function columnsOf(): array
{
    $table = test()->getJson('/admin/table', ['X-Vanda' => 'true'])->assertOk()->json('layout.0');

    return [array_column(array_filter($table['columns'], fn (array $column) => $column['hidden'] ?? false), 'name'), $table['columnChoice'] ?? null];
}

function saveColumns(array $hidden, string $table = 'layout.0'): Illuminate\Testing\TestResponse
{
    $page = test()->getJson('/admin/table', ['X-Vanda' => 'true'])->json();

    return test()->postJson('/admin/table', ['type' => 'columns', 'table' => $table, 'hidden' => $hidden, 'snapshot' => $page['snapshot'], 'state' => []], ['X-Vanda' => 'true']);
}

it('draws the table with the columns the user hid, the defaults until they choose', function () {
    $this->actingAs($this->anna);
    expect(columnsOf())->toBe([['country'], 'none']);

    Preferences::put($this->anna, 'columns:'.TableScreen::class.':layout.0', ['hidden' => ['email']]);

    // The saved choice wins over TD::defaultHidden(): country shows, email hides.
    expect(columnsOf())->toBe([['email'], 'saved']);
});

it('saves the hidden columns of a table, only its hideable ones, for the signed-in user', function () {
    $this->actingAs($this->anna);

    saveColumns(['email', 'name', 'password', 'country', 7])->assertOk()->assertExactJson(['hidden' => ['email', 'country']]);

    expect(Preferences::get($this->anna, 'columns:'.TableScreen::class.':layout.0'))->toBe(['hidden' => ['email', 'country']])
        ->and(columnsOf())->toBe([['email', 'country'], 'saved']);

    // Showing every column again is a choice too.
    saveColumns([])->assertOk();
    expect(columnsOf())->toBe([[], 'saved']);
});

it('keeps each user\'s columns apart', function () {
    $this->actingAs($this->anna);
    saveColumns(['email']);

    $this->actingAs($this->boris);
    expect(columnsOf())->toBe([['country'], 'none']);
});

it('refuses a table the screen does not have and a guest', function () {
    $this->actingAs($this->anna);
    saveColumns(['email'], 'layout.9')->assertNotFound();
    saveColumns(['email'], 'columns:App\\Screens\\Other:layout.0')->assertNotFound();

    auth()->logout();
    saveColumns(['email'])->assertForbidden();
    expect(columnsOf())->toBe([['country'], null])
        ->and(DB::table(Preferences::TABLE)->count())->toBe(0);
});

it('keeps the columns of two tables on one screen apart', function () {
    $first = Layout::table()->id('orders');
    $second = Layout::table()->id('returns');

    expect($first->columnsKey(TableScreen::class))->toBe('columns:'.TableScreen::class.':orders')
        ->and($second->columnsKey(TableScreen::class))->not->toBe($first->columnsKey(TableScreen::class));
});
