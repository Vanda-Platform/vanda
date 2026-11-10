<?php

declare(strict_types=1);

namespace Vanda;

use Closure;
use Illuminate\Http\Request;
use Vanda\Access\PermissionGroup;
use Vanda\Access\PermissionRegistry;
use Vanda\Extensions\Extension;
use Vanda\Extensions\ExtensionRegistry;
use Vanda\Http\AssetController;
use Vanda\Navigation\MenuItem;
use Vanda\Navigation\Shell;
use Vanda\Screen\ScreenRegistry;
use Vanda\Testing\ScreenTester;

/**
 * Entry points for application code.
 */
final class Panel
{
    /**
     * Sidebar items, resolved on every request (so labels may use the current user):
     *
     *     Panel::menu(fn () => [MenuItem::make('Users')->icon('users')->to(UserListScreen::class)]);
     *
     * @param Closure(): iterable<MenuItem> $items
     */
    public static function menu(Closure $items): void
    {
        app(Shell::class)->menu($items);
    }

    /**
     * Permissions of the panel, shown in role and user forms and usable as Gate abilities:
     *
     *     Panel::permissions(fn () => [PermissionGroup::make('System')->add('platform.users', 'Users')]);
     *
     * @param Closure(): iterable<PermissionGroup> $groups
     */
    public static function permissions(Closure $groups): void
    {
        app(PermissionRegistry::class)->define($groups);
    }

    /**
     * A package's screens, permissions and search in the panel (see Extension). The package calls
     * it in the boot() of its service provider; the same class registered again replaces the first,
     * so the application configures an extension in its own boot():
     *
     *     Panel::extension(SettingsExtension::make()->page('shop', ShopSettingsScreen::class));
     *
     * Its screens are mounted in a routes file with Route::extension(SettingsExtension::class) inside Route::vanda().
     *
     * @param Extension|class-string<Extension> $extension
     */
    public static function extension(Extension|string $extension): void
    {
        if (is_string($extension)) {
            if (! is_subclass_of($extension, Extension::class)) {
                throw VandaException::notAnExtension($extension);
            }
            $extension = app($extension);
        }

        app(ExtensionRegistry::class)->register($extension);
    }

    /**
     * Extensions registered with Panel::extension(), in the order they came.
     *
     * @return list<Extension>
     */
    public static function extensions(): array
    {
        return app(ExtensionRegistry::class)->all();
    }

    /**
     * Records the ⌘K palette can find. Called with what the user typed (2+ characters);
     * Search::apply() finds it whatever the case and alphabet («анна» → «Анна»):
     *
     *     Panel::search(fn (string $query) => Search::apply(Page::query(), ['title'], $query)->limit(5)->get()
     *         ->map(fn (Page $page) => SearchResult::make($page->title)->icon('file')->to(PageEditScreen::class, ['page' => $page])));
     *
     * @param Closure(string $query): iterable<SearchResult> $provider
     */
    public static function search(Closure $provider): void
    {
        app(Shell::class)->addSearch($provider);
    }

    /**
     * Photo of the signed-in user in the sidebar; without it the initials are shown:
     *
     *     Panel::userAvatar(fn (User $user) => $user->attachments('avatar')->first()?->url());
     *
     * @param Closure(mixed $user): ?string $resolver
     */
    public static function userAvatar(Closure $resolver): void
    {
        app(Shell::class)->userAvatar($resolver);
    }

    /**
     * The tab icon: config('vanda.favicon') (a URL) or Vanda's orchid in the brand colour,
     * inlined so it needs no request and no cache busting.
     */
    public static function favicon(): string
    {
        $custom = config('vanda.favicon');
        if (is_string($custom) && $custom !== '') {
            return $custom;
        }

        // The orchid takes the panel's brand colour, as in the menu.
        $svg = (string) file_get_contents(__DIR__.'/../resources/images/vanda-symbol.svg');
        $brand = (string) config('vanda.brand', '#12805C');
        $svg = preg_match('/^#[0-9a-f]{3,8}$/i', $brand) === 1 ? str_replace('#12805C', $brand, $svg) : $svg;

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    /**
     * The signed-in user's own page: their name in the menu leads there, and so does the
     * "change password" link of the weak password warning.
     *
     *     Panel::profile(fn (User $user) => Panel::url(UserProfileScreen::class));
     *
     * @param Closure(mixed $user): ?string $resolver
     */
    public static function profile(Closure $resolver): void
    {
        app(Shell::class)->profile($resolver);
    }

    /**
     * Where to go after signing in: config('vanda.home'), else the first menu item.
     */
    public static function home(): string
    {
        $home = config('vanda.home');

        if (is_string($home) && class_exists($home)) {
            return self::url($home);
        }
        if (is_string($home) && $home !== '') {
            return url($home);
        }

        $first = app(Shell::class)->items()[0] ?? null;

        return $first?->getHref() ?? url('/');
    }

    /**
     * URL of a screen with route parameters (models are converted to route keys).
     *
     * @param class-string $screen
     * @param array<string, mixed> $parameters
     */
    public static function url(string $screen, array $parameters = []): string
    {
        return app(ScreenRegistry::class)->url($screen, $parameters);
    }

    /**
     * Drive a screen in tests without a browser:
     *
     *     Panel::test(UserEditScreen::class, ['user' => $user])
     *         ->set('form.email', 'not-an-email')
     *         ->call('save')
     *         ->assertHasErrors(['form.email']);
     *
     * @param class-string $screen
     * @param array<string, mixed> $parameters
     */
    public static function test(string $screen, array $parameters = []): ScreenTester
    {
        return new ScreenTester($screen, $parameters);
    }

    /** @var array{script: string, styles: list<string>, version: string}|null */
    private static ?array $entry = null;

    /** @var list<string> URL prefixes registered with Route::vanda() */
    private static array $prefixes = [];

    /**
     * @internal called by Route::vanda()
     */
    public static function registerPrefix(string $prefix): void
    {
        self::$prefixes[] = trim($prefix, '/');
    }

    /**
     * URL prefixes of the panels registered with Route::vanda(), e.g. ['admin'].
     *
     * @return list<string>
     */
    public static function prefixes(): array
    {
        return array_values(array_unique(self::$prefixes));
    }

    /**
     * The request is for a screen or for a URL under a Route::vanda() prefix,
     * so errors are shown as a Vanda page.
     */
    public static function isPanelRequest(Request $request): bool
    {
        if (($request->route()?->defaults['_vanda_screen'] ?? null) !== null) {
            return true;
        }

        foreach (self::$prefixes as $prefix) {
            if ($prefix === '' || $request->is($prefix, $prefix.'/*')) {
                return true;
            }
        }

        return false;
    }

    /**
     * URLs of the renderer's script and styles, from the build manifest.
     *
     * @return array{script: string, styles: list<string>, version: string}
     */
    public static function entry(): array
    {
        if (self::$entry !== null) {
            return self::$entry;
        }

        $manifest = AssetController::directory().'/.vite/manifest.json';
        if (! is_file($manifest)) {
            throw new VandaException("The renderer is not built: {$manifest} is missing. Run `npm run build` in the Vanda repository.");
        }

        $json = (string) file_get_contents($manifest);
        $chunks = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        $entry = collect($chunks)->first(static fn (array $chunk) => $chunk['isEntry'] ?? false);
        $url = static fn (string $file) => route('vanda.asset', ['file' => $file]);
        // One stylesheet for the whole renderer (cssCodeSplit: false) is listed as "style.css".
        $styles = [...($entry['css'] ?? []), ...(isset($chunks['style.css']) ? [$chunks['style.css']['file']] : [])];

        return self::$entry = [
            'script' => $url($entry['file']),
            'styles' => array_map($url, $styles),
            'version' => substr(md5($json), 0, 12),
        ];
    }
}
