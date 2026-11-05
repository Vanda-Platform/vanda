<?php

declare(strict_types=1);

use Vanda\Access\Impersonation;
use Vanda\Layouts\Persona;
use Vanda\Layouts\Tone;
use Vanda\Navigation\SearchResult;
use Vanda\Notifications\PanelMessage;
use Vanda\Schema\SchemaContext;
use Vanda\Tests\Fixtures\ProfileScreen;
use Vanda\Tests\Fixtures\ShowcaseScreen;
use Vanda\Tests\Fixtures\User;
use Vanda\Vanda;

beforeEach(function () {
    $this->admin = User::query()->create(['name' => 'Vera Mironova', 'email' => 'vera@example.com']);
    $this->anna = User::query()->create(['name' => 'Anna Smirnova', 'email' => 'anna@example.com']);
});

it('puts an avatar and a status badge into the screen header', function () {
    $page = Vanda::test(ProfileScreen::class, ['user' => $this->anna])->page();

    expect($page['avatar'])->toBe(['name' => 'Anna Smirnova', 'initials' => 'AS', 'hue' => crc32('Anna Smirnova') % 6, 'image' => '/photos/1.png'])
        ->and($page['badge'])->toMatchArray(['type' => 'badge', 'id' => 'header.badge', 'label' => 'Active', 'tone' => 'success', 'dot' => true]);
});

it('describes option hints, the avatar upload, action rows and an empty table', function () {
    $screen = Vanda::test(ProfileScreen::class, ['user' => $this->anna]);

    expect($screen->node('timezone')['options'])->toBe([['value' => 'Europe/Berlin', 'label' => 'Europe/Berlin', 'hint' => 'UTC+01:00']])
        ->and($screen->node('photo'))->toMatchArray(['type' => 'upload', 'accept' => 'image/*', 'avatar' => ['initials' => 'AS', 'hue' => crc32('Anna Smirnova') % 6]])
        ->and($screen->node('photo'))->not->toHaveKey('multiple')
        ->and($screen->node('layout.3')['empty'])->toMatchArray(['type' => 'empty', 'title' => 'Nobody yet', 'icon' => 'users', 'description' => 'Invite someone.']);

    // The "Purge" row has no allowed button, so only one row is drawn.
    $rows = $screen->node('layout.2')['children'];
    expect(array_column($rows, 'title'))->toBe(['Sign in as'])
        ->and($rows[0]['children'][0])->toMatchArray(['type' => 'button', 'id' => 'action.impersonate']);
});

it('signs in as another user and back, showing who is behind the session', function () {
    $this->actingAs($this->admin);
    $page = $this->getJson('/admin/profile/'.$this->anna->id, ['X-Vanda' => 'true'])->json();

    $this->postJson('/admin/profile/'.$this->anna->id, [
        'type' => 'action', 'action' => 'impersonate', 'args' => [], 'state' => [], 'snapshot' => $page['snapshot'],
        'token' => collect($page['layout'][2]['children'][0]['children'])->first()['action']['token'],
    ], ['X-Vanda' => 'true'])->assertOk()->assertJson(['redirect' => '/admin/showcase']);

    expect(auth()->id())->toBe($this->anna->id)
        ->and(Impersonation::impersonator()?->getKey())->toBe($this->admin->id);

    $this->getJson('/admin/showcase', ['X-Vanda' => 'true'])
        ->assertJsonPath('app.user.name', 'Anna Smirnova')
        ->assertJsonPath('app.user.impersonator.name', 'Vera Mironova');

    $this->post('/panel/vanda/impersonation/stop')->assertRedirect();

    expect(auth()->id())->toBe($this->admin->id)
        ->and(Impersonation::isActive())->toBeFalse();
});

it('refuses to sign in as yourself', function () {
    $this->actingAs($this->admin);

    expect(fn () => Impersonation::loginAs($this->admin))->toThrow(\Vanda\VandaException::class);
});

it('lists notifications for the bell and marks them read', function () {
    $this->admin->notify(PanelMessage::make('Import finished')->message('18 products added')->href('/admin/showcase')->tone(Tone::Success));
    $this->admin->notify(PanelMessage::make('Mail bounced')->tone(Tone::Danger));
    $this->actingAs($this->admin);

    expect($this->getJson('/admin/showcase', ['X-Vanda' => 'true'])->json('app.notifications.unread'))->toBe(2);

    $list = $this->getJson('/panel/vanda/notifications')->assertOk()->json();
    $import = collect($list['items'])->firstWhere('title', 'Import finished');
    expect($list['unread'])->toBe(2)
        ->and($import)->toMatchArray(['message' => '18 products added', 'href' => '/admin/showcase', 'tone' => 'success', 'read' => false]);

    $this->postJson('/panel/vanda/notifications/read', ['id' => $import['id']])->assertJsonPath('unread', 1);
    $this->postJson('/panel/vanda/notifications/read')->assertJsonPath('unread', 0);
});

it('stores panel messages in the shape of Orchid\'s DashboardMessage', function () {
    $this->admin->notify(PanelMessage::make('Hello')->message('World'));

    expect($this->admin->notifications()->first()->data)->toMatchArray(['title' => 'Hello', 'message' => 'World', 'action' => null, 'type' => 'accent'])
        ->toHaveKey('time');
});

it('shows a person\'s photo wherever the person appears', function () {
    $persona = Persona::make('Anna Smirnova')->image('/photos/anna.png')->id('p')->toSchema(new SchemaContext(new ShowcaseScreen, []));
    $result = SearchResult::make('Anna Smirnova')->href('/anna')->avatar('Anna Smirnova', '/photos/anna.png')->toArray();

    expect($persona)->toMatchArray(['initials' => 'AS', 'image' => '/photos/anna.png'])
        ->and($result)->toMatchArray(['initials' => 'AS', 'image' => '/photos/anna.png']);

    Vanda::userAvatar(fn (User $user) => "/photos/{$user->id}.png");
    $this->actingAs($this->admin);

    $this->getJson('/admin/showcase', ['X-Vanda' => 'true'])
        ->assertJsonPath('app.user.avatar', ['name' => 'Vera Mironova', 'initials' => 'VM', 'hue' => crc32('Vera Mironova') % 6, 'image' => "/photos/{$this->admin->id}.png"]);
});
