<?php

declare(strict_types=1);

namespace Vanda\OrchidCompat;

use Illuminate\Support\ServiceProvider;
use Vanda\Panel;

/**
 * What the code of an Orchid project expects from orchid/platform outside the admin screens:
 * route('platform.…') names and config('platform.prefix'). Registered by package discovery.
 */
final class OrchidCompatServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/orchid-compat.php', 'orchid-compat');
    }

    public function boot(): void
    {
        $this->publishes([__DIR__.'/../config/orchid-compat.php' => config_path('orchid-compat.php')], 'orchid-compat');

        RouteAliases::register(array_map('strval', (array) config('orchid-compat.route_aliases', [])));

        // The panel prefix is known once the routes are loaded.
        $this->app->booted(function (): void {
            $prefix = Panel::prefixes()[0] ?? null;
            if ($prefix !== null && config('platform.prefix') === null) {
                config(['platform.prefix' => '/'.$prefix]);
            }
        });
    }
}
