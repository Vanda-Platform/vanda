<?php

declare(strict_types=1);

use Vanda\Tests\Fixtures\User;
use Vanda\Tests\Fixtures\UserEditScreen;
use Vanda\Tests\Fixtures\UserListScreen;
use Vanda\Vanda;

beforeEach(function () {
    $this->user = User::query()->create(['name' => 'Anna', 'email' => 'anna@example.com', 'country' => 'RU', 'city' => 1]);
    User::query()->create(['name' => 'Igor', 'email' => 'igor@example.com']);
});

it('validates Form rules before the action runs', function () {
    Vanda::test(UserEditScreen::class, ['user' => $this->user])
        ->set('form.email', 'igor@example.com')
        ->call('save')
        ->assertHasErrors(['form.email']);

    expect($this->user->fresh()->email)->toBe('anna@example.com');
});

it('requires a field only while requiredWhen is true and the field is shown', function () {
    Vanda::test(UserEditScreen::class, ['user' => $this->user])
        ->set('form.country', 'DE')
        ->call('save')
        ->assertHasErrors(['form.vat']);

    Vanda::test(UserEditScreen::class, ['user' => $this->user])
        ->set('form.country', 'RU')
        ->set('form.city', 2)
        ->call('save')
        ->assertHasNoErrors()
        ->assertToast('User saved')
        ->assertRedirect(Vanda::url(UserListScreen::class));

    expect($this->user->fresh()->city)->toBe(2);
});

it('ignores browser values for locked, disabled and unrendered paths', function () {
    $url = '/admin/users/'.$this->user->id.'/edit';
    $page = $this->getJson($url, ['X-Vanda' => 'true'])->json();
    $state = $page['state'];
    $state['form']['admin'] = true;          // disabled field
    $state['form']['notes'] = 'injected';    // not on the screen
    $state['user'] = ['__model' => User::class, 'key' => 2]; // locked model
    $state['form']['name'] = 'Anna K.';

    $save = collect($page['commandBar'])->firstWhere('id', 'action.save')['action'];
    $this->postJson($url, ['type' => 'action', 'action' => 'save', 'token' => $save['token'], 'snapshot' => $page['snapshot'], 'state' => $state])
        ->assertOk()
        ->assertJsonPath('redirect', Vanda::url(UserListScreen::class));

    expect($this->user->fresh()->name)->toBe('Anna K.')
        ->and(User::query()->find(2)->name)->toBe('Igor');
});

it('only calls rendered #[Action] methods with matching arguments', function () {
    $page = $this->getJson('/admin/users', ['X-Vanda' => 'true'])->json();
    $button = $page['layout'][0]['rows'][0]['cells']['actions']['action'];
    $request = fn (array $overrides) => $this->postJson('/admin/users', [
        'type' => 'action', 'snapshot' => $page['snapshot'], 'state' => [], ...$button, 'action' => $button['name'], ...$overrides,
    ]);

    $request(['action' => 'helper'])->assertNotFound();
    $request(['args' => ['user' => 2]])->assertForbidden();
    $request([])->assertOk()->assertJsonPath('toast.message', 'User deleted');

    expect(User::query()->find($this->user->id))->toBeNull();
});

it('describes a screen for agents with vanda:inspect', function () {
    $this->artisan('vanda:inspect', ['target' => '/admin/users/'.$this->user->id.'/edit'])
        ->expectsOutputToContain('Actions: save(), delete()')
        ->expectsOutputToContain('resetCity() ← form.country')
        ->expectsOutputToContain('dependsOn: form.country')
        ->assertSuccessful();
});
