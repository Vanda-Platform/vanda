<?php

declare(strict_types=1);

namespace Vanda\Settings;

use Illuminate\Support\ServiceProvider;
use Vanda\Extensions\ExtensionRegistry;
use Vanda\Panel;

final class SettingsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // One read of the table per request; Octane requests and queued jobs start afresh.
        $this->app->scoped(SettingsStore::class);
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'vanda-settings');
        $this->publishesMigrations([__DIR__.'/../database/migrations' => database_path('migrations')], 'vanda-settings-migrations');

        if (config('vanda.migrations', true)) {
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        }

        // Without pages until the application registers it again with its own.
        if (! $this->app->make(ExtensionRegistry::class)->has(SettingsExtension::class)) {
            Panel::extension(SettingsExtension::make());
        }
    }
}
