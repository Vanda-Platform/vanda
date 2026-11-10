<?php

declare(strict_types=1);

namespace Vanda\Settings\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;
use ReflectionClass;
use Vanda\Panel;
use Vanda\Schema\Node;
use Vanda\Schema\SchemaCheck;
use Vanda\Settings\SettingsExtension;
use Vanda\Settings\SettingsServiceProvider;
use Vanda\Settings\Tests\Fixtures\MailSettingsScreen;
use Vanda\Settings\Tests\Fixtures\ShopSettingsScreen;
use Vanda\Settings\Tests\Fixtures\User;
use Vanda\VandaServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [\Inertia\ServiceProvider::class, VandaServiceProvider::class, SettingsServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('vanda.dev', true);
        $app['config']->set('vanda.migrations', false);
        $app['config']->set('auth.providers.users.model', User::class);
    }

    protected function defineDatabaseMigrations(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        $core = dirname((string) (new ReflectionClass(VandaServiceProvider::class))->getFileName(), 2);
        foreach ([...glob($core.'/database/migrations/*.php'), ...glob(__DIR__.'/../database/migrations/*.php')] as $migration) {
            (require $migration)->up();
        }
    }

    protected function defineRoutes($router): void
    {
        // As an application does: its pages in a service provider, the extension in its routes.
        Panel::extension(SettingsExtension::make()
            ->page('shop', ShopSettingsScreen::class)
            ->page('mail', MailSettingsScreen::class));

        Route::vanda('admin', function (): void {
            Route::extension(SettingsExtension::class);
        });
    }

    protected function setUp(): void
    {
        parent::setUp();
        Node::$captureSource = true;
        SchemaCheck::$enabled = true;
    }
}
