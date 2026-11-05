<?php

namespace App\Providers;

use App\Models\User;
use App\Vanda\Screens\DashboardScreen;
use App\Vanda\Screens\RoleListScreen;
use App\Vanda\Screens\ShowcaseScreen;
use App\Vanda\Screens\UserListScreen;
use Illuminate\Support\Carbon;
use Illuminate\Support\ServiceProvider;
use Vanda\Access\PermissionGroup;
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

        Vanda::permissions(fn () => [
            PermissionGroup::make('Доступ')
                ->add('platform.users', 'Пользователи')
                ->add('platform.roles', 'Роли'),
        ]);

        Vanda::menu(fn () => [
            MenuItem::make('Обзор')->icon('dashboard')->to(DashboardScreen::class),
            MenuItem::make('Пользователи')->icon('users')->group('Доступ')->badge(User::query()->count())->to(UserListScreen::class),
            MenuItem::make('Роли')->icon('shield')->group('Доступ')->to(RoleListScreen::class),
            MenuItem::make('Витрина компонентов')->icon('grid')->group('Разработка')->to(ShowcaseScreen::class),
        ]);
    }
}
