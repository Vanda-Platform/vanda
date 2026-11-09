<?php

declare(strict_types=1);

namespace Vanda\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;
use Vanda\Access\PermissionGroup;
use Vanda\Activity\ActivityListScreen;
use Vanda\Activity\ActivityScreen;
use Vanda\Navigation\MenuItem;
use Vanda\Schema\Node;
use Vanda\Schema\SchemaCheck;
use Vanda\Tests\Fixtures\SecuredScreen;
use Vanda\Tests\Fixtures\ShowcaseScreen;
use Vanda\Tests\Fixtures\User;
use Vanda\Tests\Fixtures\UserEditScreen;
use Vanda\Tests\Fixtures\UserListScreen;
use Vanda\Panel;
use Vanda\VandaServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [\Inertia\ServiceProvider::class, VandaServiceProvider::class];
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
            $table->string('country')->nullable();
            $table->unsignedInteger('city')->nullable();
            $table->string('vat')->nullable();
            $table->timestamps();
        });

        Schema::create('articles', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->text('body')->nullable();
            $table->boolean('published')->default(false);
            $table->string('token')->nullable();
            $table->unsignedInteger('views')->default(0);
            $table->string('status')->nullable();
            $table->timestamps();
        });

        (require __DIR__.'/../database/migrations/2026_11_05_000001_create_vanda_access_tables.php')->up();
        (require __DIR__.'/../database/migrations/2026_11_06_000001_create_vanda_attachment_tables.php')->up();
        (require __DIR__.'/../database/migrations/2026_11_07_000001_create_vanda_notifications_table.php')->up();
        (require __DIR__.'/../database/migrations/2026_11_08_000001_create_vanda_activity_table.php')->up();
    }

    protected function defineRoutes($router): void
    {
        Route::middleware('web')->prefix('admin')->group(function (): void {
            Route::screen('users', UserListScreen::class)->name('users');
            Route::screen('users/create', UserEditScreen::class)->name('users.create');
            Route::screen('users/{user}/edit', UserEditScreen::class)->name('users.edit');
            Route::screen('showcase', ShowcaseScreen::class)->name('showcase');
            Route::screen('table', Fixtures\TableScreen::class)->name('table');
            Route::screen('upload', Fixtures\UploadScreen::class)->name('upload');
            Route::screen('profile/{user}', Fixtures\ProfileScreen::class)->name('profile');
            Route::screen('articles/{article}', Fixtures\ArticleScreen::class)->name('articles.edit');
            Route::screen('activity', ActivityListScreen::class)->name('activity');
            Route::screen('activity/{activity}', ActivityScreen::class)->name('activity.show');
        });

        Route::vanda('panel', function (): void {
            Route::screen('secured', SecuredScreen::class)->name('secured');
        });
    }

    protected function setUp(): void
    {
        parent::setUp();
        Node::$captureSource = true;
        SchemaCheck::$enabled = true;

        Panel::permissions(fn () => [
            PermissionGroup::make('System')
                ->add('platform.users', 'Users')
                ->add('platform.users.delete', 'Delete users')
                ->add('platform.activity', 'Activity'),
        ]);
        Panel::menu(fn () => [
            MenuItem::make('Secured')->to(SecuredScreen::class),
            MenuItem::make('Showcase')->to(ShowcaseScreen::class),
        ]);
    }
}
