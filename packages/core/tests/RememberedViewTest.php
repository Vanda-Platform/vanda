<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Vanda\Panel;
use Vanda\Screen\RememberedView;
use Vanda\Support\Preferences;
use Vanda\Tests\Fixtures\TableScreen;
use Vanda\Tests\Fixtures\User;

beforeEach(function () {
    $this->anna = User::query()->create(['name' => 'Anna', 'email' => 'anna@example.com', 'country' => 'DE']);
    User::query()->create(['name' => 'Boris', 'email' => 'boris@test.dev', 'country' => 'FR']);
    User::query()->create(['name' => 'Clara', 'email' => 'clara@example.com', 'country' => 'DE']);
});

function savedView(User $user): ?array
{
    return Preferences::get($user, RememberedView::key(TableScreen::class, []));
}

it('saves the view a user leaves a table in, cleaned like the URL', function () {
    $this->actingAs($this->anna)
        ->get('/admin/table?q=a&filter[country]=DE&filter[country2]=x&sort=-name&perPage=5&page=2&tab=3')
        ->assertOk();

    expect(savedView($this->anna))->toBe(['q' => 'a', 'filter' => ['country' => 'DE'], 'sort' => '-name', 'perPage' => '5', 'page' => '2']);
});

it('brings the view back on a bare visit, without the page when coming from elsewhere', function () {
    $this->actingAs($this->anna)->get('/admin/table?filter[country]=DE&perPage=5&page=2');

    $this->get('/admin/table?tab=2', ['referer' => 'http://localhost/admin/pages'])
        ->assertRedirect('http://localhost/admin/table?tab=2&filter%5Bcountry%5D=DE&perPage=5');
    // A typed address has no referer: page 1 as well.
    $this->get('/admin/table')->assertRedirect('http://localhost/admin/table?filter%5Bcountry%5D=DE&perPage=5');
    // Leaving the bare address of the list for itself (the menu item of the open page) starts over at page 1 too.
    $this->get('/admin/table', ['referer' => 'http://localhost/admin/table?page=2'])->assertRedirect('http://localhost/admin/table?filter%5Bcountry%5D=DE&perPage=5');
});

it('keeps the page on the way back from the list\'s own screens', function () {
    $this->actingAs($this->anna)->get('/admin/table?filter[country]=DE&page=2');

    $this->get('/admin/table', ['referer' => 'http://localhost/admin/table/7/edit'])
        ->assertRedirect('http://localhost/admin/table?filter%5Bcountry%5D=DE&page=2');
    $this->get('/admin/table', ['referer' => 'http://elsewhere.test/admin/table/7/edit'])
        ->assertRedirect('http://localhost/admin/table?filter%5Bcountry%5D=DE');
    // Inertia visits are redirected the same way; the browser follows to the full address.
    $this->get('/admin/table', ['X-Inertia' => 'true', 'X-Inertia-Version' => (string) Panel::entry()['version'], 'referer' => 'http://localhost/admin/table/create'])
        ->assertRedirect('http://localhost/admin/table?filter%5Bcountry%5D=DE&page=2');
});

it('saves an empty view after «Reset», so the old filters do not come back', function () {
    $this->actingAs($this->anna)->get('/admin/table?filter[country]=DE');

    // The table's own controls mark their visits; «Reset» leaves no parameters at all.
    $this->get('/admin/table', [RememberedView::HEADER => '1'])->assertOk();

    expect(savedView($this->anna))->toBe([]);
    $this->get('/admin/table')->assertOk();
});

it('lets an address with the table\'s parameters win over the saved view', function () {
    $this->actingAs($this->anna)->get('/admin/table?filter[country]=DE&sort=name');

    $this->get('/admin/table?filter[country]=FR')->assertOk();

    expect(savedView($this->anna))->toBe(['filter' => ['country' => 'FR']]);
});

it('drops saved values the table no longer takes', function () {
    Preferences::put($this->anna, RememberedView::key(TableScreen::class, []), [
        'q' => 'cla',
        'filter' => ['country' => 'XX', 'email' => 'clara', 'created' => ['start' => 'yesterday', 'end' => '2026-03-01'], 'gone' => '1'],
        'sort' => 'password',
        'perPage' => '1000',
        'page' => 'two',
    ]);

    $this->actingAs($this->anna)->get('/admin/table')
        ->assertRedirect('http://localhost/admin/table?q=cla&filter%5Bemail%5D=clara&filter%5Bcreated%5D%5Bend%5D=2026-03-01');
});

it('keeps a view per user and leaves guests, JSON requests and forgetful tables alone', function () {
    $boris = User::query()->where('name', 'Boris')->firstOrFail();
    $this->actingAs($this->anna)->get('/admin/table?filter[country]=DE');
    $this->actingAs($boris)->get('/admin/table')->assertOk();

    // JSON page descriptions (tools, tests) neither save nor restore.
    $this->actingAs($this->anna)->getJson('/admin/table?filter[country]=FR', ['X-Vanda' => 'true'])->assertOk();
    $this->getJson('/admin/table', ['X-Vanda' => 'true'])->assertOk();
    expect(savedView($this->anna))->toBe(['filter' => ['country' => 'DE']]);

    // A link prefetched on hover is not a visit: it does not change the saved view.
    $this->get('/admin/table?filter[country]=FR', ['Purpose' => 'prefetch'])->assertOk();
    expect(savedView($this->anna))->toBe(['filter' => ['country' => 'DE']]);

    $this->get('/admin/plain-table?q=an')->assertOk();
    $this->get('/admin/plain-table')->assertOk();

    auth()->logout();
    $this->get('/admin/table?filter[country]=FR');
    expect(DB::table(Preferences::TABLE)->count())->toBe(1);
});

it('shows the last page when the remembered one is past the end', function () {
    $table = $this->getJson('/admin/table?page=9', ['X-Vanda' => 'true'])->json('layout.0');

    expect($table['pagination'])->toMatchArray(['page' => 2, 'lastPage' => 2])
        ->and(array_column($table['rows'], 'key'))->toBe(['3']);
});
