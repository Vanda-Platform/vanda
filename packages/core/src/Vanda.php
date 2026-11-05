<?php

declare(strict_types=1);

namespace Vanda;

use Closure;
use Vanda\Http\AssetController;
use Vanda\Navigation\MenuItem;
use Vanda\Navigation\Shell;
use Vanda\Screen\ScreenRegistry;
use Vanda\Testing\ScreenTester;

/**
 * Entry points for application code.
 */
final class Vanda
{
    /**
     * Sidebar items, resolved on every request (so labels may use the current user):
     *
     *     Vanda::menu(fn () => [MenuItem::make('Users')->icon('users')->to(UserListScreen::class)]);
     *
     * @param Closure(): iterable<MenuItem> $items
     */
    public static function menu(Closure $items): void
    {
        app(Shell::class)->menu($items);
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
     *     Vanda::test(UserEditScreen::class, ['user' => $user])
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

    public static function asset(string $file): string
    {
        $path = AssetController::directory().'/'.$file;
        $version = is_file($path) ? substr(md5((string) filemtime($path)), 0, 8) : 'missing';

        return route('vanda.asset', ['file' => $file]).'?v='.$version;
    }
}
