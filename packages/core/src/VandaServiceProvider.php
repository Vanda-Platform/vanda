<?php

declare(strict_types=1);

namespace Vanda;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Vanda\Console\InspectCommand;
use Vanda\Console\ScreensCommand;
use Vanda\Http\AssetController;
use Vanda\Http\ScreenController;
use Vanda\Navigation\Shell;
use Vanda\Schema\Node;
use Vanda\Screen\Screen;
use Vanda\Screen\ScreenRegistry;

final class VandaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/vanda.php', 'vanda');
        $this->app->singleton(ScreenRegistry::class);
        $this->app->singleton(Shell::class);
    }

    public function boot(): void
    {
        Node::$captureSource = (bool) (config('vanda.dev') ?? $this->app->isLocal());

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'vanda');
        $this->publishes([__DIR__.'/../config/vanda.php' => config_path('vanda.php')], 'vanda-config');

        Route::get('vanda/assets/{file}', AssetController::class)
            ->where('file', '[A-Za-z0-9_.-]+')
            ->name('vanda.asset');

        Route::macro('screen', function (string $uri, string $screen) {
            if (! is_subclass_of($screen, Screen::class)) {
                throw VandaException::notAScreen($screen);
            }

            return Route::match(['GET', 'HEAD', 'POST'], $uri, ScreenController::class)
                ->defaults('_vanda_screen', $screen);
        });

        if ($this->app->runningInConsole()) {
            $this->commands([InspectCommand::class, ScreensCommand::class]);
        }
    }
}
