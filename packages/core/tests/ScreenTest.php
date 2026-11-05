<?php

declare(strict_types=1);

use Vanda\Actions\Button;
use Vanda\Screen\Screen;
use Vanda\Tests\Fixtures\User;
use Vanda\Tests\Fixtures\UserEditScreen;
use Vanda\Tests\Fixtures\UserListScreen;
use Vanda\Vanda;
use Vanda\VandaException;

beforeEach(function () {
    $this->user = User::query()->create(['name' => 'Anna', 'email' => 'anna@example.com', 'country' => 'RU', 'city' => 1]);
    User::query()->create(['name' => 'Igor', 'email' => 'igor@example.com']);
});

it('describes the page as IR with stable ids and sources', function () {
    $page = $this->getJson('/admin/users/'.$this->user->id.'/edit', ['X-Vanda' => 'true'])->assertOk()->json();

    expect($page['schema'])->toBe('vanda/v1')
        ->and($page['title'])->toBe('Edit user')
        ->and($page['state'])->toHaveKey('form')->not->toHaveKey('user')
        ->and($page['state']['form']['email'])->toBe('anna@example.com')
        ->and(array_column($page['commandBar'], 'id'))->toBe(['action.delete', 'action.save'])
        ->and($page['commandBar'][1]['action'])->toHaveKeys(['name', 'token'])
        ->and($page['commandBar'][1]['source'])->toMatch('/UserEditScreen\.php:\d+$/');

    $grid = $page['layout'][0]['children'][0];
    expect(array_column($grid['children'], 'id'))->toBe(['form.name', 'form.email', 'form.country', 'form.city', 'form.vat', 'form.admin'])
        ->and($grid['children'][3]['dependsOn'])->toBe(['form.country'])
        ->and(array_column($grid['children'][3]['options'], 'label'))->toBe(['Moscow', 'Kazan']);
});

it('renders the HTML shell with the embedded page', function () {
    $this->get('/admin/users')
        ->assertOk()
        ->assertSee('data-page="app"', false)
        ->assertSee(Vanda::entry()['script'], false)
        ->assertSee(Vanda::entry()['styles'][0], false);
});

it('hides server-hidden buttons and links to other screens', function () {
    $create = Vanda::test(UserEditScreen::class)->page();
    $list = Vanda::test(UserListScreen::class)->page();

    expect(array_column($create['commandBar'], 'id'))->toBe(['action.save'])
        ->and($create['title'])->toBe('Create user')
        ->and($list['commandBar'][0]['href'])->toEndWith('/admin/users/create')
        ->and($list['layout'][0]['rows'])->toHaveCount(2)
        ->and($list['layout'][0]['rows'][0]['cells']['actions']['action']['args'])->toBe(['user' => $this->user->id]);
});

it('refuses buttons that point to methods without #[Action]', function () {
    $screen = new class extends Screen
    {
        public function layout(): iterable
        {
            return [Button::make('Run')->action($this->helper(...))];
        }

        public function helper(): void {}
    };

    $screen->layout();
})->throws(VandaException::class, 'has no #[Action] attribute');
