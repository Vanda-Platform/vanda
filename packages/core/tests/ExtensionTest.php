<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;
use Vanda\Access\PermissionGroup;
use Vanda\Access\PermissionRegistry;
use Vanda\Activity\Activity;
use Vanda\Activity\ActivityOptions;
use Vanda\Activity\RecordsActivity;
use Vanda\Attributes\Can;
use Vanda\Extensions\Extension;
use Vanda\Layouts\Text;
use Vanda\Navigation\SearchResult;
use Vanda\Panel;
use Vanda\Screen\Screen;
use Vanda\Tests\Fixtures\ArticleStatus;
use Vanda\Tests\Fixtures\User;
use Vanda\VandaException;

#[Can('reports.view')]
final class ReportsScreen extends Screen
{
    public function title(): string
    {
        return 'Reports';
    }

    public function layout(): iterable
    {
        return [Text::make('Monthly reports')];
    }
}

final class ReportsExtension extends Extension
{
    public string $label = 'View reports';

    public function name(): string
    {
        return 'reports';
    }

    public function routes(): void
    {
        Route::screen('/', ReportsScreen::class)->name('index');
    }

    public function permissions(): iterable
    {
        return [PermissionGroup::make('Reports')->add('reports.view', $this->label)];
    }
}

final class FinderExtension extends Extension
{
    public function name(): string
    {
        return 'finder';
    }

    public function search(string $query): iterable
    {
        return [SearchResult::make("Report «{$query}»")->to(ReportsScreen::class)];
    }
}

final class NamedLikeReports extends Extension
{
    public function name(): string
    {
        return 'reports';
    }
}

final class BadlyNamedExtension extends Extension
{
    public function name(): string
    {
        return 'My Reports';
    }
}

final class ArticleWithMeta extends Model
{
    use RecordsActivity;

    protected $table = 'articles';

    protected $guarded = [];

    protected $casts = ['body' => 'array'];

    public function activityOptions(): ActivityOptions
    {
        return ActivityOptions::make('Статья')->everywhere()->only('body')->expand('body', ['status' => ArticleStatus::class])->secret('api_key')->labels(['color' => 'Цвет']);
    }
}

/** Declares the routes of a panel at /ext the way a routes file does. */
function extensionRoutes(Closure $routes): void
{
    Route::vanda('ext', $routes);
    app('router')->getRoutes()->refreshNameLookups();
}

beforeEach(function () {
    $this->user = User::query()->create(['name' => 'Vera', 'email' => 'vera@example.com', 'permissions' => ['reports.view' => true]]);
});

it('mounts the screens of an extension under the panel and its own name', function () {
    Panel::extension(ReportsExtension::class);
    extensionRoutes(fn () => Route::extension(ReportsExtension::class));

    expect(Route::has('ext.reports.index'))->toBeTrue()
        ->and(Panel::url(ReportsScreen::class))->toBe(url('/ext/reports'));

    $this->get('/ext/reports')->assertRedirectContains('/login');   // behind the panel's sign-in
    $page = $this->actingAs($this->user)->getJson('/ext/reports', ['X-Vanda' => 'true'])->assertOk()->json();
    expect($page['title'])->toBe('Reports');
});

it('mounts an extension at another address when the application asks', function () {
    Panel::extension(ReportsExtension::class);
    extensionRoutes(fn () => Route::extension(ReportsExtension::class, 'tools/monthly'));

    expect(Panel::url(ReportsScreen::class))->toBe(url('/ext/tools/monthly'))
        ->and(Route::has('ext.reports.index'))->toBeTrue();
});

it('adds the permissions of an extension, and the application can replace its instance', function () {
    Panel::extension(ReportsExtension::class);
    expect(app(PermissionRegistry::class)->labels())->toHaveKey('reports.view', 'View reports');

    $configured = new ReportsExtension;
    $configured->label = 'Open monthly reports';
    Panel::extension($configured);

    app(PermissionRegistry::class)->define(fn () => []);   // drops the cached groups, as a new request would
    expect(app(PermissionRegistry::class)->labels())->toHaveKey('reports.view', 'Open monthly reports')
        ->and(Panel::extensions())->toHaveCount(1)
        ->and(ReportsExtension::registered())->toBe($configured);
});

it('turns the ⌘K search on only for an extension that searches', function () {
    Panel::extension(ReportsExtension::class);
    extensionRoutes(fn () => Route::extension(ReportsExtension::class));
    $page = $this->actingAs($this->user)->getJson('/ext/reports', ['X-Vanda' => 'true'])->json();
    expect($page['app']['search'])->toBeNull();

    Panel::extension(FinderExtension::class);
    $page = $this->getJson('/ext/reports', ['X-Vanda' => 'true'])->json();
    expect($page['app']['search'])->not->toBeNull();

    $results = $this->getJson('/ext/vanda/search?q='.rawurlencode('июль'), ['X-Vanda' => 'true'])->assertOk()->json();
    expect(collect($results)->flatten()->implode(' '))->toContain('Report «июль»');
});

it('explains what is wrong with an extension and how to fix it', function (Closure $call, string $message) {
    expect($call)->toThrow(VandaException::class, $message);
})->with([
    'outside of a panel' => [fn () => Route::extension(ReportsExtension::class), "Route::vanda('admin', function () { Route::extension(ReportsExtension::class); });"],
    'not registered' => [fn () => extensionRoutes(fn () => Route::extension(ReportsExtension::class)), 'add Panel::extension(ReportsExtension::class)'],
    'not an extension' => [fn () => Panel::extension(ReportsScreen::class), 'ReportsScreen does not extend Vanda\Extensions\Extension'],
    'a name that cannot be a URL' => [fn () => Panel::extension(BadlyNamedExtension::class), 'BadlyNamedExtension::name() returns "My Reports"'],
    'a name another extension has' => [function () {
        Panel::extension(ReportsExtension::class);
        Panel::extension(NamedLikeReports::class);
    }, 'ReportsExtension and NamedLikeReports are both named "reports"'],
]);

it('records each key of a JSON attribute by itself', function () {
    $article = ArticleWithMeta::query()->create(['title' => 'Акция', 'body' => ['color' => 'red', 'api_key' => 'k-1', 'size' => 2, 'status' => 'draft', 'tags' => []]]);
    $article->update(['body' => ['color' => 'blue', 'api_key' => 'k-2', 'size' => 2, 'status' => 'published', 'tags' => ['a']]]);

    $entry = Activity::query()->latest('id')->first();
    expect($entry->diff)->toBe([
        'color' => ['red', 'blue'],
        'api_key' => [null, null],
        'status' => [ArticleStatus::Draft->label(), ArticleStatus::Published->label()],
        'tags' => [null, '["a"]'],
    ])->and($entry->properties['secret'])->toBe(['api_key'])
        ->and($entry->properties['labels'])->toBe(['color' => 'Цвет']);
});

it('writes no entry when a JSON attribute only changed the order of its keys', function () {
    $article = ArticleWithMeta::query()->create(['title' => 'Акция', 'body' => ['color' => 'red', 'size' => 2]]);
    $before = Activity::query()->count();

    $article->update(['body' => ['size' => 2, 'color' => 'red']]);

    expect(Activity::query()->count())->toBe($before);
});
