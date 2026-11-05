<?php

namespace App\Providers;

use App\Models\Page;
use App\Models\User;
use App\Vanda\Screens\DashboardScreen;
use App\Vanda\Screens\PageEditScreen;
use App\Vanda\Screens\PageListScreen;
use App\Vanda\Screens\RoleListScreen;
use App\Vanda\Screens\ShowcaseScreen;
use App\Vanda\Screens\UserEditScreen;
use App\Vanda\Screens\UserListScreen;
use Illuminate\Support\Carbon;
use Illuminate\Support\ServiceProvider;
use Vanda\Access\PermissionGroup;
use Vanda\Navigation\MenuItem;
use Vanda\Navigation\SearchResult;
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
            PermissionGroup::make('Сайт')
                ->add('platform.pages', 'Страницы'),
        ]);

        // ⌘K finds people and pages; results that lead to screens the user may not open are hidden.
        Vanda::search(fn (string $query) => User::query()
            ->where(fn ($where) => $where->where('name', 'like', "%{$query}%")->orWhere('email', 'like', "%{$query}%"))
            ->limit(5)->get()
            ->map(fn (User $user) => SearchResult::make($user->name)->subtitle($user->email)->avatar($user->name)->group('Пользователи')
                ->to(UserEditScreen::class, ['user' => $user])));

        Vanda::search(fn (string $query) => Page::query()->where('title', 'like', "%{$query}%")->limit(5)->get()
            ->map(fn (Page $page) => SearchResult::make($page->title)->icon('file')->group('Страницы')
                ->to(PageEditScreen::class, ['page' => $page])));

        Vanda::menu(fn () => [
            MenuItem::make('Обзор')->icon('dashboard')->to(DashboardScreen::class),
            MenuItem::make('Пользователи')->icon('users')->group('Доступ')->badge(User::query()->count())->to(UserListScreen::class),
            MenuItem::make('Роли')->icon('shield')->group('Доступ')->to(RoleListScreen::class),
            MenuItem::make('Страницы')->icon('file')->group('Сайт')->to(PageListScreen::class),
            MenuItem::make('Витрина компонентов')->icon('grid')->group('Разработка')->to(ShowcaseScreen::class),
        ]);
    }
}
