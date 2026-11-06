<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Hash;
use Vanda\Auth\LoginScreen;
use Vanda\Tests\Fixtures\User;
use Vanda\Panel;

beforeEach(function () {
    $this->admin = User::query()->create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => Hash::make('secret-pass')]);
});

it('sends guests to the sign-in screen', function () {
    $this->get('/panel/secured')->assertRedirect('/panel/login');
    $this->postJson('/panel/secured', ['type' => 'react'], ['X-Vanda' => 'true'])->assertUnauthorized();
});

it('signs in and goes to the page that was asked for', function () {
    $this->get('/panel/secured');

    $page = $this->getJson('/panel/login', ['X-Vanda' => 'true'])->assertOk()->json();
    expect($page['chrome'])->toBe('card')->and($page['app']['menu'])->toBe([]);

    $button = collect($page['layout'])->firstWhere('type', 'button')['action'];
    $state = ['form' => ['email' => 'admin@example.com', 'password' => 'secret-pass', 'remember' => true]];

    $this->postJson('/panel/login', ['type' => 'action', 'action' => 'login', 'token' => $button['token'], 'snapshot' => $page['snapshot'], 'state' => $state])
        ->assertOk()
        ->assertJsonPath('redirect', '/panel/secured');

    $this->assertAuthenticatedAs($this->admin);
});

it('never sends a secret back to the browser', function () {
    $tester = Panel::test(LoginScreen::class)
        ->set('form.email', 'admin@example.com')
        ->set('form.password', 'wrong-pass')
        ->call('login')
        ->assertHasErrors(['form.email']);

    $snapshot = base64_decode(strtr(explode('.', $tester->lastResult()['snapshot'])[0], '-_', '+/'));

    expect($snapshot)->toContain('admin@example.com')->not->toContain('wrong-pass')
        ->and(Panel::test(LoginScreen::class)->page()['state']['form'])->not->toHaveKey('password');
    $this->assertGuest();
});

it('stays on this panel after signing in, whatever host the page asked for had', function () {
    session()->put('url.intended', 'https://evil.example/panel/secured?tab=2');
    $page = $this->getJson('/panel/login', ['X-Vanda' => 'true'])->json();
    $button = collect($page['layout'])->firstWhere('type', 'button')['action'];

    $this->postJson('/panel/login', ['type' => 'action', 'action' => 'login', 'token' => $button['token'], 'snapshot' => $page['snapshot'], 'state' => ['form' => ['email' => 'admin@example.com', 'password' => 'secret-pass']]])
        ->assertJsonPath('redirect', '/panel/secured?tab=2');
});

it('signs out', function () {
    $this->actingAs($this->admin)->post('/panel/logout')->assertRedirect('/panel/login');
    $this->assertGuest();
});

/**
 * Signs in through the login screen the way the browser does and returns the next page's IR.
 */
function signInAs(string $email, string $password): array
{
    $page = test()->getJson('/panel/login', ['X-Vanda' => 'true'])->json();
    $button = collect($page['layout'])->firstWhere('type', 'button')['action'];
    test()->postJson('/panel/login', ['type' => 'action', 'action' => 'login', 'token' => $button['token'], 'snapshot' => $page['snapshot'], 'state' => ['form' => ['email' => $email, 'password' => $password]]])->assertOk();

    return test()->getJson('/admin/showcase', ['X-Vanda' => 'true'])->json();
}

it('tells which passwords are weak', function (string $password, bool $weak) {
    expect(Vanda\Auth\WeakPassword::check($password, 'ivan.petrov@example.com'))->toBe($weak);
})->with([
    'too short' => ['Xk7$p2', true],
    'from the leaked lists' => ['qwerty123', true],
    'the login inside' => ['ivan.petrov2026!', true],
    'one kind of character' => ['correcthorsebat', true],
    'few different characters' => ['aaaaAAAA1111', true],
    'a common word with a year' => ['Password2026!', true],
    'dictionary pieces' => ['secret-pass', true],
    'long and mixed' => ['Kofe-v-zernah-2026', false],
    'a long passphrase' => ['лиловая орхидея цветёт зимой', false],
]);

it('shows a red strip on production after signing in with a weak password, until it is changed', function () {
    Panel::profile(fn ($user) => '/panel/profile');
    signInAs('admin@example.com', 'secret-pass');
    $this->app->detectEnvironment(fn () => 'production');

    expect($this->getJson('/admin/showcase', ['X-Vanda' => 'true'])->json('app.alerts'))->toBe([[
        'tone' => 'danger',
        'text' => __('vanda::auth.weak_password'),
        'link' => ['label' => __('vanda::auth.change_password'), 'href' => '/panel/profile'],
    ]]);

    // A request loads the user afresh; the test app keeps the guard's copy, so forget it.
    $this->admin->forceFill(['password' => Hash::make('Kofe-v-zernah-2026')])->save();
    auth()->forgetGuards();
    $this->actingAs($this->admin->fresh());
    expect($this->getJson('/admin/showcase', ['X-Vanda' => 'true'])->json('app.alerts'))->toBe([]);
});

it('keeps quiet about weak passwords outside production and about strong ones', function () {
    expect(signInAs('admin@example.com', 'secret-pass')['app']['alerts'])->toBe([]);

    $this->post('/panel/logout');
    $this->admin->forceFill(['password' => Hash::make('Kofe-v-zernah-2026')])->save();
    signInAs('admin@example.com', 'Kofe-v-zernah-2026');
    $this->app->detectEnvironment(fn () => 'production');
    expect($this->getJson('/admin/showcase', ['X-Vanda' => 'true'])->json('app.alerts'))->toBe([]);
});
