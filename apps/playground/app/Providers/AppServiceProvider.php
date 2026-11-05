<?php

namespace App\Providers;

use App\Models\User;
use App\Vanda\Screens\UserListScreen;
use Illuminate\Support\Carbon;
use Illuminate\Support\ServiceProvider;
use Vanda\Navigation\MenuItem;
use Vanda\Vanda;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Carbon::setLocale(config('app.locale'));

        Vanda::menu(fn () => [
            MenuItem::make('Пользователи')
                ->icon('users')
                ->group('Доступ')
                ->badge(User::query()->count())
                ->to(UserListScreen::class),
        ]);
    }
}
