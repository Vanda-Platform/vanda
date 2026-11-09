<?php

declare(strict_types=1);

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Vanda\Access\Authorizer;
use Vanda\Access\Role;
use Vanda\Actions\Link;
use Vanda\Attributes\Can;
use Vanda\Panel;
use Vanda\Schema\Node;
use Vanda\Screen\ActionCall;
use Vanda\Screen\Screen;
use Vanda\Screen\ScreenRuntime;
use Vanda\Tests\Fixtures\Article;
use Vanda\Tests\Fixtures\GuardedScreen;
use Vanda\Tests\Fixtures\User;
use Vanda\VandaException;
use Vanda\VandaServiceProvider;

beforeEach(function () {
    GuardedScreen::$hooks = 0;
    $this->user = User::query()->create(['name' => 'Ivan', 'email' => 'ivan@example.com']);
    $this->admin = Role::query()->create(['slug' => 'admin', 'name' => 'Admin', 'permissions' => ['platform.users' => true, 'platform.users.delete' => true]]);
});

/**
 * Ids of every node of the page, the command bar's and the layout's.
 *
 * @param list<array<string, mixed>> $nodes
 * @return list<string>
 */
function guardedIds(array $nodes): array
{
    $ids = [];
    foreach ($nodes as $node) {
        $ids[] = (string) ($node['id'] ?? '');
        $ids = [...$ids, ...guardedIds($node['children'] ?? [])];
    }

    return $ids;
}

it('takes no browser value for a field inside a section or card hidden on the server', function () {
    Panel::test(GuardedScreen::class)
        ->tamper('item.internal', 'Hacked')
        ->tamper('item.audit', 'Hacked')
        ->set('item.title', 'New')
        ->call('save')
        ->assertToast('Saved New, discount 0, internal Old, audit Old');
});

it('refuses to set a field inside a hidden section in a test, as the browser never sends it', function () {
    Panel::test(GuardedScreen::class)->set('item.internal', 'New');
})->throws(VandaException::class, 'the field "item.internal" is read-only, disabled or hidden on this screen');

it('takes a value only while visibleWhen shows the field, read on what the browser sent', function () {
    Panel::test(GuardedScreen::class)
        ->tamper('item.discount', '50')
        ->call('save')
        ->assertToast('Saved Old, discount 0, internal Old, audit Old');

    // The order of the edits does not matter: the browser sends all its values each time.
    Panel::test(GuardedScreen::class)
        ->set('item.discount', '50')
        ->set('item.kind', 'sale')
        ->call('save')
        ->assertToast('Saved Old, discount 50, internal Old, audit Old');
});

it('keeps a forbidden action hidden even when ->visible(true) comes after ->action() or ->load()', function () {
    $ids = static function (): array {
        $page = ScreenRuntime::boot(GuardedScreen::class)->ir();

        return guardedIds([...$page['commandBar'], ...$page['layout']]);
    };

    expect($ids())->not->toContain('action.purge')
        ->not->toContain('toggle.purge')
        ->not->toContain('link.secured')
        ->toContain('action.save');

    $this->user->replaceRoles([$this->admin->id]);
    $this->actingAs($this->user->fresh());

    expect($ids())->toContain('action.purge')->toContain('toggle.purge')->toContain('link.secured');
});

it('hides a node by ->visible(false) after ->action(), and keeps the rule for ->visible(true) before it', function () {
    $screen = app(GuardedScreen::class);

    expect(Vanda\Actions\Button::make('Purge')->visible(true)->action($screen->purge(...))->isVisible())->toBeFalse()
        ->and(Vanda\Actions\Button::make('Save')->action($screen->save(...))->visible(false)->isVisible())->toBeFalse()
        ->and(Vanda\Actions\Button::make('Save')->visible(false)->action($screen->save(...))->isVisible())->toBeFalse()
        ->and(Vanda\Actions\Button::make('Save')->action($screen->save(...))->visible(true)->isVisible())->toBeTrue();
});

it('hides a link to a screen whose #[Can] checks the model passed to it', function () {
    $screen = new #[Can('articles.open', model: 'article')] class extends Screen
    {
        public function layout(): iterable
        {
            return [];
        }
    };
    Gate::define('articles.open', static fn (User $user, Article $article) => $article->published);
    $this->actingAs($this->user);

    $draft = Article::query()->create(['title' => 'Draft']);
    $published = Article::query()->create(['title' => 'Out', 'published' => true]);

    expect(Authorizer::allowsScreen($screen::class, ['article' => $draft]))->toBeFalse()
        ->and(Authorizer::allowsScreen($screen::class, ['article' => $published]))->toBeTrue()
        // Only the id: the screen checks itself when it is opened.
        ->and(Authorizer::allowsScreen($screen::class, ['article' => $draft->id]))->toBeTrue();

    // A link that is pointed elsewhere afterwards is no longer forbidden.
    expect(Link::make('Secured')->to(Vanda\Tests\Fixtures\SecuredScreen::class)->isVisible())->toBeFalse()
        ->and(Link::make('Secured')->to(Vanda\Tests\Fixtures\SecuredScreen::class)->href('/elsewhere')->isVisible())->toBeTrue();
});

it('checks #[Can] of an action before #[Updated] hooks and validation run', function () {
    $runtime = ScreenRuntime::boot(GuardedScreen::class);
    $token = ActionCall::sign(GuardedScreen::class, 'purge', []);
    $state = $runtime->state();
    $state['item']['title'] = 'Changed';
    $state['item']['rows'] = [['name' => '', 'note' => '']];

    expect(fn () => $runtime->act('purge', [], $token, $state))->toThrow(AuthorizationException::class)
        ->and(GuardedScreen::$hooks)->toBe(0);

    $this->user->replaceRoles([$this->admin->id]);
    $this->actingAs($this->user->fresh());
    [$status, $body] = ScreenRuntime::boot(GuardedScreen::class)->act('purge', [], $token, $state);

    expect($status)->toBe(422)
        ->and($body['errors'])->toHaveKey('item.rows.0.name')
        ->and(GuardedScreen::$hooks)->toBe(1);
});

it('validates ->required() of a field in every Repeater row', function () {
    Panel::test(GuardedScreen::class)
        ->set('item.rows', [['name' => 'First', 'note' => ''], ['name' => '', 'note' => 'no name']])
        ->call('save')
        ->assertHasErrors(['item.rows.1.name']);

    Panel::test(GuardedScreen::class)
        ->set('item.rows', [['name' => 'First', 'note' => '']])
        ->call('save')
        ->assertHasNoErrors();
});

it('checks pages in the application\'s own tests unless vanda.dev says otherwise', function () {
    config()->set('vanda.dev', null);
    expect(VandaServiceProvider::devMode($this->app))->toBeTrue();

    $this->app->detectEnvironment(static fn () => 'production');
    expect(VandaServiceProvider::devMode($this->app))->toBeFalse();

    config()->set('vanda.dev', true);
    expect(VandaServiceProvider::devMode($this->app))->toBeTrue();

    $this->app->detectEnvironment(static fn () => 'testing');
    config()->set('vanda.dev', false);
    expect(VandaServiceProvider::devMode($this->app))->toBeFalse();
});
