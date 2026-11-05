<?php

declare(strict_types=1);

use Vanda\Access\Role;
use Vanda\Tests\Fixtures\ShowcaseScreen;
use Vanda\Tests\Fixtures\User;
use Vanda\Vanda;

beforeEach(function () {
    User::query()->create(['name' => 'Anna', 'email' => 'anna@example.com']);
    User::query()->create(['name' => 'Igor', 'email' => 'igor@example.com']);
    User::query()->create(['name' => 'Maria', 'email' => 'maria@example.com']);
});

it('describes tabs, modals, repeaters and permissions in the IR', function () {
    $page = Vanda::test(ShowcaseScreen::class)->page();
    $types = array_column($page['layout'], 'type');

    expect($types)->toBe(['tabs', 'keyvalue', 'permissions', 'modal', 'modaltoggle', 'table'])
        ->and(array_column($page['layout'][0]['children'], 'title'))->toBe(['Main', 'Specs'])
        ->and($page['layout'][0]['children'][1]['children'][0]['fields'][0])->toMatchArray(['type' => 'input', 'path' => 'name'])
        ->and($page['layout'][2]['groups'][0]['items'][0])->toBe(['key' => 'platform.users', 'label' => 'Users'])
        ->and($page['layout'][3])->toMatchArray(['id' => 'quick', 'buttonIds' => ['action.saveQuick']])
        ->and($page['layout'][4]['action']['name'])->toBe('loadQuick')
        ->and($page['state']['form'])->not->toHaveKey('token');
});

it('validates only the modal when its button is pressed, and only the page otherwise', function () {
    Vanda::test(ShowcaseScreen::class)
        ->call('saveQuick', from: 'action.saveQuick')
        ->assertHasErrors(['quick.title'])
        ->call('save')
        ->assertHasErrors(['form.name']);

    $tester = Vanda::test(ShowcaseScreen::class)->set('quick.title', 'Hello')->call('saveQuick', from: 'action.saveQuick');
    expect($tester->lastResult()['toast']['message'])->toBe('Quick saved: Hello');
});

it('fills a modal through a load action and asks the browser to open it', function () {
    $tester = Vanda::test(ShowcaseScreen::class)->call('loadQuick', ['id' => 7]);

    expect($tester->lastResult()['openModal'])->toBe('quick')
        ->and($tester->page()['state']['quick']['title'])->toBe('Item 7');
});

it('cleans structured values from the browser and validates repeater rows', function () {
    $tester = Vanda::test(ShowcaseScreen::class)
        ->set('form.name', 'Kit')
        ->set('form.specs', [['name' => 'Weight', 'value' => '1 kg', 'hack' => 'x'], ['value' => 'no name'], ['name' => 'a'], ['name' => 'b']])
        ->set('form.utm', [['key' => 'utm_source', 'value' => 'mail', 'extra' => 1], 'junk'])
        ->set('form.permissions', ['platform.users' => '1', 'unknown.perm' => true, 'platform.users.delete' => null]);

    expect($tester->page()['state']['form']['specs'])->toBe([['name' => 'Weight', 'value' => '1 kg'], ['value' => 'no name'], ['name' => 'a']])
        ->and($tester->page()['state']['form']['utm'])->toBe([['key' => 'utm_source', 'value' => 'mail']])
        ->and($tester->page()['state']['form']['permissions'])->toBe(['platform.users' => true]);

    $tester->call('save')->assertHasErrors(['form.specs.1.name']);
});

it('searches, sorts and paginates a table from the URL', function () {
    $table = fn (string $query) => collect($this->getJson('/admin/showcase'.$query, ['X-Vanda' => 'true'])->json('layout'))->firstWhere('type', 'table');

    expect(array_column(array_column($table('?q=ig')['rows'], 'cells'), 'name'))->toHaveCount(1)
        ->and($table('?q=ig')['search']['value'])->toBe('ig')
        ->and(array_map(fn ($row) => $row['cells']['name']['text'], $table('?sort=-name')['rows']))->toBe(['Maria', 'Igor', 'Anna'])
        ->and($table('?sort=-name')['sort'])->toBe('-name')
        ->and($table('?sort=-password'))->not->toHaveKey('sort')
        ->and($table('')['bulk'][0]['action'])->toMatchArray(['name' => 'deleteMany', 'bulk' => true]);
});

it('runs bulk actions for the selected ids only through a bulk token', function () {
    $ids = User::query()->whereIn('name', ['Anna', 'Igor'])->pluck('id')->map(fn ($id) => (string) $id)->all();

    Vanda::test(ShowcaseScreen::class)->callBulk('deleteMany', $ids)->assertToast('Deleted 2');

    expect(User::query()->pluck('name')->all())->toBe(['Maria']);
});
