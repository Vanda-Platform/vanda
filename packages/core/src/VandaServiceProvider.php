<?php

declare(strict_types=1);

namespace Vanda;

use Closure;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Vanda\Access\Authorizer;
use Vanda\Access\PermissionRegistry;
use Vanda\Activity\ActivityLog;
use Vanda\Auth\LoginScreen;
use Vanda\Console\AdminCommand;
use Vanda\Console\CheckCommand;
use Vanda\Console\InspectCommand;
use Vanda\Console\ScreensCommand;
use Vanda\Http\AssetController;
use Vanda\Http\Authenticate;
use Vanda\Http\InertiaMiddleware;
use Vanda\Http\LogoutController;
use Vanda\Http\NotificationsController;
use Vanda\Http\RedirectIfAuthenticated;
use Vanda\Http\ScreenController;
use Vanda\Http\SearchController;
use Vanda\Http\StopImpersonationController;
use Vanda\Navigation\Shell;
use Vanda\Schema\Node;
use Vanda\Schema\SchemaCheck;
use Vanda\Screen\Screen;
use Vanda\Screen\ScreenRegistry;

final class VandaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/vanda.php', 'vanda');
        $this->app->singleton(ScreenRegistry::class);
        $this->app->singleton(Shell::class);
        $this->app->singleton(PermissionRegistry::class);
        $this->app->singleton(ActivityLog::class);
    }

    /**
     * Development mode (sources in the IR, every page checked by SchemaCheck): `vanda.dev` when
     * it is set, else on locally and in the application's tests (APP_ENV=testing).
     *
     *     <env name="VANDA_DEV" value="false"/>   // phpunit.xml: tests without the page checks
     */
    public static function devMode(Application $app): bool
    {
        return (bool) ($app['config']->get('vanda.dev') ?? ($app->environment('local') || $app->runningUnitTests()));
    }

    public function boot(): void
    {
        Node::$captureSource = self::devMode($this->app);
        // The same development mode checks every page for paths, modals and icons that do not exist.
        SchemaCheck::$enabled = Node::$captureSource;

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'vanda');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'vanda');
        $this->publishes([__DIR__.'/../config/vanda.php' => config_path('vanda.php')], 'vanda-config');
        $this->publishesMigrations([__DIR__.'/../database/migrations' => database_path('migrations')], 'vanda-migrations');

        if (config('vanda.migrations', true)) {
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        }

        // Registered Vanda permissions become Gate abilities: $user->can('platform.users').
        Gate::before(function ($user, string $ability) {
            if (Authorizer::$allowAll) {
                return true;
            }
            if (! method_exists($user, 'hasAccess') || ! app(PermissionRegistry::class)->has($ability)) {
                return null;
            }

            return $user->hasAccess($ability);
        });

        // 403, 404, 419… of panel pages are shown in the panel's style instead of Laravel's default page.
        $this->callAfterResolving(ExceptionHandler::class, function (ExceptionHandler $handler): void {
            if (! $handler instanceof Handler) {
                return;
            }

            $handler->renderable(function (HttpExceptionInterface $exception, Request $request) {
                if ($request->expectsJson() || $request->header('X-Vanda') === 'true' || ! Panel::isPanelRequest($request)) {
                    return null;
                }

                $home = rescue(static fn () => Panel::home(), url('/'), report: false);

                return response()->view('vanda::error', ['status' => $exception->getStatusCode(), 'home' => $home], $exception->getStatusCode(), $exception->getHeaders());
            });
        });

        Route::get('vanda/assets/{file}', AssetController::class)
            ->where('file', '[A-Za-z0-9_.-]+')
            ->name('vanda.asset');

        Route::macro('screen', function (string $uri, string $screen) {
            if (! is_subclass_of($screen, Screen::class)) {
                throw VandaException::notAScreen($screen);
            }

            return Route::match(['GET', 'HEAD', 'POST'], $uri, ScreenController::class)
                ->defaults('_vanda_screen', $screen)
                ->middleware(InertiaMiddleware::class);
        });

        /*
         * The panel under a prefix: sign-in, sign-out and your screens behind auth.
         *
         *     Route::vanda('admin', function () {
         *         Route::screen('users', UserListScreen::class)->name('users');
         *     });
         */
        Route::macro('vanda', function (string $prefix, Closure $routes) {
            Panel::registerPrefix($prefix);
            Route::middleware(config('vanda.middleware', ['web']))->prefix($prefix)->group(function () use ($routes) {
                Route::screen('login', LoginScreen::class)->name('vanda.login')->middleware(RedirectIfAuthenticated::class);
                Route::post('logout', LogoutController::class)->name('vanda.logout');
                Route::post('vanda/impersonation/stop', StopImpersonationController::class)->name('vanda.impersonation.stop');
                Route::middleware(Authenticate::class)->group(function () use ($routes): void {
                    Route::get('vanda/search', SearchController::class)->name('vanda.search');
                    Route::get('vanda/notifications', [NotificationsController::class, 'index'])->name('vanda.notifications');
                    Route::post('vanda/notifications/read', [NotificationsController::class, 'read'])->name('vanda.notifications.read');
                    $routes();
                });
            });
        });

        if ($this->app->runningInConsole()) {
            $this->commands([AdminCommand::class, CheckCommand::class, InspectCommand::class, ScreensCommand::class]);
        }
    }
}
