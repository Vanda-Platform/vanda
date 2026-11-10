<?php

declare(strict_types=1);

use Symfony\Component\HttpKernel\Exception\HttpException;
use Vanda\Access\Role;
use Vanda\Activity\Activity;
use Vanda\Actions\ModalToggle;
use Vanda\Layouts\Layout;
use Vanda\Layouts\TD;
use Vanda\Panel;
use Vanda\Schema\SchemaCheck;
use Vanda\Schema\SchemaContext;
use Vanda\Screen\ScreenRuntime;
use Vanda\Tests\Fixtures\RowActionScreen;
use Vanda\Tests\Fixtures\User;
use Vanda\VandaException;

beforeEach(function () {
    RowActionScreen::$purged = [];
    User::query()->create(['name' => 'Anna', 'email' => 'anna@example.com']);
    User::query()->create(['name' => 'Boris', 'email' => 'boris@example.com']);
});

it('gives every row of a table the modal toggle built for it, its load signed for that row', function () {
    $rows = Panel::test(RowActionScreen::class)->node('layout.0')['rows'];

    expect($rows[0]['action'])->toMatchArray(['type' => 'modaltoggle', 'id' => 'layout.0.1.action', 'label' => 'Open', 'modal' => 'seo'])
        ->and($rows[0]['action']['action'])->toMatchArray(['name' => 'loadSeo', 'args' => ['id' => 1]])
        ->and($rows[1]['action']['action']['args'])->toBe(['id' => 2])
        ->and($rows[0]['action']['action']['token'])->not->toBe($rows[1]['action']['action']['token'])
        ->and($rows[0])->not->toHaveKey('link');
});

it('opens the modal of the clicked row with the token of that row only', function () {
    $runtime = ScreenRuntime::boot(RowActionScreen::class, [], '/admin/rows');
    $action = Panel::test(RowActionScreen::class)->node('layout.0')['rows'][1]['action'];

    [$status, $body] = $runtime->act('loadSeo', $action['action']['args'], $action['action']['token'], [], $action['id']);

    expect($status)->toBe(200)
        ->and($body['openModal'])->toBe('seo')
        ->and($body['page']['state']['seoTitle'])->toBe('Boris');

    // The token of row 2 does not open row 1.
    expect(fn () => $runtime->act('loadSeo', ['id' => 1], $action['action']['token'], [], $action['id']))
        ->toThrow(HttpException::class);
});

it('leaves the loading of a modal from a row out of the activity log', function () {
    $action = Panel::test(RowActionScreen::class)->node('layout.0')['rows'][0]['action'];

    ScreenRuntime::boot(RowActionScreen::class, [], '/admin/rows')
        ->act('loadSeo', $action['action']['args'], $action['action']['token'], [], $action['id']);

    expect(Activity::query()->count())->toBe(0);

    // The page draws the call only as a modal load: the same call signed as a button press is refused.
    $press = Vanda\Screen\ActionCall::sign(RowActionScreen::class, 'loadSeo', ['id' => 1]);
    expect(fn () => ScreenRuntime::boot(RowActionScreen::class, [], '/admin/rows')->act('loadSeo', ['id' => 1], $press, [], $action['id']))
        ->toThrow(HttpException::class);
    expect(Activity::query()->count())->toBe(0);
});

it('makes rows of a tree run their button and leaves rows without one or with a forbidden one alone', function () {
    $rows = Panel::test(RowActionScreen::class)->node('layout.1')['rows'];

    expect(array_filter(array_column($rows, 'action')))->toBe([]);

    $user = User::query()->find(1);
    $user->replaceRoles([Role::query()->create(['slug' => 'admin', 'name' => 'Admin', 'permissions' => ['platform.users.delete' => true]])->id]);
    $this->actingAs($user->fresh());

    $rows = collect(Panel::test(RowActionScreen::class)->node('layout.1')['rows'])->keyBy('key');

    expect($rows['5']['action'])->toMatchArray(['type' => 'button', 'id' => 'layout.1.5.action', 'label' => 'Purge'])
        ->and($rows['5']['action']['confirm'])->toBe(['title' => 'Purge?', 'text' => 'Gone for good.'])
        ->and($rows['5']['action']['action']['args'])->toBe(['id' => 5])
        ->and($rows['6'])->not->toHaveKey('action');

    Panel::test(RowActionScreen::class)->call('purge', ['id' => 5], from: 'layout.1.5.action')->assertToast('Purged');
    expect(RowActionScreen::$purged)->toBe([5]);
});

it('opens a page from a row with a link node', function () {
    expect(Panel::test(RowActionScreen::class)->node('links')['rows'][0]['action'])
        ->toMatchArray(['type' => 'link', 'id' => 'links.7.action', 'href' => '/docs/7']);
});

it('refuses a row link and a row action on the same table or tree', function () {
    expect(fn () => Layout::table()->rowLink(fn () => '/a')->rowAction(fn () => null))
        ->toThrow(VandaException::class, 'Layout::table() has both ->rowLink() and ->rowAction()')
        ->and(fn () => Layout::tree()->rowAction(fn () => null)->rowLink(fn () => '/a'))
        ->toThrow(VandaException::class, 'Layout::tree() has both ->rowLink() and ->rowAction()');
});

it('says what a row action must return', function () {
    $table = Layout::table()->rows([['id' => 1, 'name' => 'A']])->columns([TD::make('name', 'Name')])->rowAction(fn () => 'open')->id('t');
    $screen = new RowActionScreen;

    expect(fn () => $table->toSchema(new SchemaContext($screen, [])))
        ->toThrow(VandaException::class, 'The closure given to Layout::table()->rowAction() returned string. Return the node a click on the row runs');
});

it('checks the modal a row action opens', function () {
    $table = Layout::table()->rows([['id' => 1, 'name' => 'A']])->columns([TD::make('name', 'Name')])
        ->rowAction(fn () => ModalToggle::make('Open')->modal('seoo'))->id('t');
    $screen = new RowActionScreen;
    $ir = ['layout' => [$table->toSchema(new SchemaContext($screen, [])), ['type' => 'modal', 'id' => 'seo']]];

    expect(SchemaCheck::problems($screen, $ir)[0])->toContain('modaltoggle "Open" at ')
        ->toContain('opens the modal "seoo", but this screen has no Layout::modal(\'seoo\', …). Did you mean "seo"?');
});

it('keeps a row link working as before', function () {
    $table = Layout::table()->rows([['id' => 3, 'name' => 'A']])->columns([TD::make('name', 'Name')])->rowLink(fn (array $row) => '/rows/'.$row['id'])->id('t');

    expect($table->toSchema(new SchemaContext(new RowActionScreen, []))['rows'][0])
        ->toMatchArray(['key' => '3', 'link' => '/rows/3'])
        ->not->toHaveKey('action');
});

