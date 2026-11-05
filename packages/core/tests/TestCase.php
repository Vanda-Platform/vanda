<?php

declare(strict_types=1);

namespace Vanda\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;
use Vanda\Schema\Node;
use Vanda\Tests\Fixtures\UserEditScreen;
use Vanda\Tests\Fixtures\UserListScreen;
use Vanda\VandaServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [VandaServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('vanda.dev', true);
    }

    protected function defineDatabaseMigrations(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('country')->nullable();
            $table->unsignedInteger('city')->nullable();
            $table->string('vat')->nullable();
            $table->timestamps();
        });
    }

    protected function defineRoutes($router): void
    {
        Route::middleware('web')->prefix('admin')->group(function (): void {
            Route::screen('users', UserListScreen::class)->name('users');
            Route::screen('users/create', UserEditScreen::class)->name('users.create');
            Route::screen('users/{user}/edit', UserEditScreen::class)->name('users.edit');
        });
    }

    protected function setUp(): void
    {
        parent::setUp();
        Node::$captureSource = true;
    }
}
