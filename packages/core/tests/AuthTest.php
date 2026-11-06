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
        ->assertJsonPath('redirect', url('/panel/secured'));

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

it('signs out', function () {
    $this->actingAs($this->admin)->post('/panel/logout')->assertRedirect('/panel/login');
    $this->assertGuest();
});
