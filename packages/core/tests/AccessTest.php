<?php

declare(strict_types=1);

use Vanda\Access\Role;
use Vanda\Tests\Fixtures\User;

beforeEach(function () {
    $this->manager = Role::query()->create(['slug' => 'manager', 'name' => 'Manager', 'permissions' => ['platform.users' => 1]]);
    $this->admin = Role::query()->create(['slug' => 'admin', 'name' => 'Admin', 'permissions' => ['platform.users' => true, 'platform.users.delete' => '1']]);
    $this->user = User::query()->create(['name' => 'Ivan', 'email' => 'ivan@example.com']);
});

it('grants permissions through roles and lets personal ones override them', function () {
    expect($this->user->hasAccess('platform.users'))->toBeFalse();

    $this->user->replaceRoles([$this->admin->id]);
    expect($this->user->fresh()->hasAccess('platform.users.delete'))->toBeTrue()
        ->and($this->user->fresh()->can('platform.users.delete'))->toBeTrue()
        ->and($this->user->fresh()->permissionSources())->toBe(['platform.users' => ['Admin'], 'platform.users.delete' => ['Admin']]);

    $this->user->update(['permissions' => ['platform.users.delete' => false]]);
    expect($this->user->fresh()->hasAccess('platform.users.delete'))->toBeFalse()
        ->and($this->user->fresh()->hasAccess('platform.users'))->toBeTrue();
});

it('closes a #[Can] screen to users without the permission', function () {
    $this->actingAs($this->user)->get('/panel/secured')->assertForbidden()->assertSee('No access')->assertSee('Error 403');
    $this->actingAs($this->user)->get('/panel/missing')->assertNotFound()->assertSee('Page not found');

    $this->user->replaceRoles([$this->manager->id]);
    $this->actingAs($this->user->fresh())->get('/panel/secured')->assertOk();
});

it('hides forbidden actions and rejects them even with a valid token', function () {
    $this->user->replaceRoles([$this->manager->id]);
    $page = $this->actingAs($this->user->fresh())->getJson('/panel/secured', ['X-Vanda' => 'true'])->json();

    expect(array_column($page['commandBar'], 'id'))->toBe(['action.ping'])
        ->and(array_column($page['app']['menu'], 'label'))->toBe(['Secured', 'Showcase']);

    $token = \Vanda\Screen\ActionCall::sign(\Vanda\Tests\Fixtures\SecuredScreen::class, 'purge', []);
    $this->postJson('/panel/secured', ['type' => 'action', 'action' => 'purge', 'token' => $token, 'snapshot' => $page['snapshot'], 'state' => []], ['X-Vanda' => 'true'])
        ->assertForbidden();
});

it('hides menu items that lead to forbidden screens', function () {
    $page = $this->actingAs($this->user)->getJson('/admin/showcase', ['X-Vanda' => 'true'])->json();

    expect(array_column($page['app']['menu'], 'label'))->toBe(['Showcase'])
        ->and($page['app']['menu'][0]['active'] ?? false)->toBeTrue();
});
