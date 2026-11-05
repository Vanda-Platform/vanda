<?php

declare(strict_types=1);

use Vanda\Access\Role;
use Vanda\Tests\Fixtures\User;

beforeEach(function () {
    User::query()->create(['name' => 'Anna', 'email' => 'anna@example.com', 'country' => 'DE', 'created_at' => '2026-01-10 10:00:00']);
    User::query()->create(['name' => 'Boris', 'email' => 'boris@test.dev', 'country' => 'FR', 'created_at' => '2026-02-10 10:00:00']);
    User::query()->create(['name' => 'Clara', 'email' => 'clara@example.com', 'country' => 'DE', 'created_at' => '2026-03-10 10:00:00']);
});

function table(string $query = ''): array
{
    $page = test()->getJson('/admin/table'.$query, ['X-Vanda' => 'true'])->assertOk()->json();

    return $page['layout'][0];
}

it('applies filters from the URL and reports them with readable values', function () {
    $table = table('?filter[country]=DE&filter[email]=clara');

    expect(array_column($table['rows'], 'key'))->toBe(['3'])
        ->and($table['filters'][0])->toMatchArray(['name' => 'country', 'value' => 'DE', 'display' => 'Germany'])
        ->and($table['filters'][1])->toMatchArray(['name' => 'email', 'value' => 'clara'])
        ->and($table['filters'][2])->not->toHaveKey('value');
});

it('runs a custom filter closure and a date range', function () {
    expect(array_column(table('?filter[domain]=example.com')['rows'], 'key'))->toBe(['1', '3'])
        ->and(array_column(table('?filter[created][start]=2026-02-01&filter[created][end]=2026-03-01')['rows'], 'key'))->toBe(['2'])
        ->and(table('?filter[created][start]=2026-02-01')['filters'][3]['display'])->toBe('01.02.2026 – …');
});

it('ignores filter values that are not among the options', function () {
    $table = table('?filter[country]=XX&filter[created][start]=yesterday');

    expect($table['rows'])->toHaveCount(2)
        ->and($table['pagination']['total'])->toBe(3)
        ->and($table['filters'][0])->not->toHaveKey('value');
});

it('counts rows of every view and marks the one matching the URL', function () {
    $all = table()['views'];
    $germany = table('?filter[country]=DE')['views'];

    expect($all)->toBe([
        ['label' => 'All', 'filters' => [], 'count' => 3, 'active' => true],
        ['label' => 'Germany', 'filters' => ['country' => 'DE'], 'count' => 2],
    ])->and($germany[1]['active'])->toBeTrue()
        ->and($germany[0])->not->toHaveKey('active');
});

it('lets the user pick only the allowed page sizes', function () {
    expect(table()['pagination'])->toMatchArray(['perPage' => 2, 'options' => [2, 5], 'lastPage' => 2])
        ->and(table('?perPage=5')['rows'])->toHaveCount(3)
        ->and(table('?perPage=1000')['rows'])->toHaveCount(2);
});

it('describes hideable columns', function () {
    expect(table()['columns'])->toBe([
        ['name' => 'name', 'label' => 'Name', 'sortable' => true],
        ['name' => 'email', 'label' => 'Email', 'hideable' => true],
        ['name' => 'country', 'label' => 'Country', 'hideable' => true, 'hidden' => true],
    ]);
});

it('puts row actions in a menu and leaves out forbidden ones', function () {
    $menu = table()['rows'][0]['actions'];

    expect($menu)->toMatchArray(['type' => 'dropdown', 'id' => 'layout.0.1.actions', 'icon' => 'more', 'variant' => 'ghost'])
        ->and(array_column($menu['children'], 'label'))->toBe(['Edit'])
        ->and($menu['children'][0]['id'])->toBe('layout.0.1.actions.0');

    $user = User::query()->find(1);
    $role = Role::query()->create(['slug' => 'admin', 'name' => 'Admin', 'permissions' => ['platform.users.delete' => true]]);
    $user->replaceRoles([$role->id]);
    $this->actingAs($user->fresh());

    expect(array_column(table()['rows'][0]['actions']['children'], 'label'))->toBe(['Edit', 'Purge']);
});

it('exports every filtered row as CSV', function () {
    $response = $this->get('/admin/table?filter[country]=DE&sort=-name&export=layout.0');

    $response->assertOk()->assertDownload('users.csv');
    expect($response->streamedContent())->toBe("\xEF\xBB\xBFName;Email;Country\nClara;clara@example.com;DE\nAnna;anna@example.com;DE\n");
});

it('refuses to export a table that is not exportable', function () {
    $this->get('/admin/users?export=layout.0')->assertNotFound();
});
