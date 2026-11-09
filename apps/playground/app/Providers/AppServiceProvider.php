<?php

namespace App\Providers;

use App\Models\Page;
use App\Models\User;
use App\Vanda\Screens\DashboardScreen;
use App\Vanda\Screens\FileBrowserScreen;
use App\Vanda\Screens\PageEditScreen;
use App\Vanda\Screens\PageListScreen;
use App\Vanda\Screens\RoleListScreen;
use App\Vanda\Screens\ShowcaseScreen;
use App\Vanda\Screens\UserEditScreen;
use App\Vanda\Screens\UserListScreen;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Vanda\Access\Impersonation;
use Vanda\Access\PermissionGroup;
use Vanda\Activity\ActivityListScreen;
use Vanda\Navigation\MenuItem;
use Vanda\Navigation\SearchResult;
use Vanda\Panel;
use Vanda\Support\Search;

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

        Panel::permissions(fn () => [
            PermissionGroup::make('Доступ')
                ->add('platform.users', 'Пользователи')
                ->add('platform.roles', 'Роли')
                ->add('platform.impersonate', 'Вход под пользователем')
                ->add('platform.activity', 'Активность'),
            PermissionGroup::make('Сайт')
                ->add('platform.pages', 'Страницы'),
        ]);

        // Signing in as someone else from the user card is not their own visit.
        Event::listen(Login::class, function (Login $event): void {
            if ($event->user instanceof User && ! Impersonation::isActive()) {
                $event->user->forceFill(['last_login_at' => now()])->saveQuietly();
            }
        });

        // The photo from the user card next to the name in the sidebar.
        Panel::userAvatar(fn (User $user) => $user->avatarUrl());
        Panel::profile(fn (User $user) => Panel::url(UserEditScreen::class, ['user' => $user]));

        // ⌘K finds people and pages; results that lead to screens the user may not open are hidden.
        Panel::search(fn (string $query) => Search::apply(User::query()->with('attachments'), ['name', 'email'], $query)
            ->limit(5)->get()
            ->map(fn (User $user) => SearchResult::make($user->name)->subtitle($user->email)->avatar($user->name, $user->avatarUrl())->group('Пользователи')
                ->to(UserEditScreen::class, ['user' => $user])));

        Panel::search(fn (string $query) => Search::apply(Page::query(), ['title'], $query)->limit(5)->get()
            ->map(fn (Page $page) => SearchResult::make($page->title)->icon('file')->group('Страницы')
                ->to(PageEditScreen::class, ['page' => $page])));

        Panel::menu(fn () => [
            MenuItem::make('Обзор')->icon('dashboard')->to(DashboardScreen::class),
            MenuItem::make('Пользователи')->icon('users')->group('Доступ')->badge(User::query()->count())->to(UserListScreen::class),
            MenuItem::make('Роли')->icon('shield')->group('Доступ')->to(RoleListScreen::class),
            MenuItem::make('Активность')->icon('activity')->group('Доступ')->to(ActivityListScreen::class),
            MenuItem::make('Страницы')->icon('file')->group('Сайт')->to(PageListScreen::class),
            MenuItem::make('Витрина компонентов')->icon('grid')->group('Разработка')->to(ShowcaseScreen::class),
            MenuItem::make('Файлы')->icon('files')->group('Разработка')->to(FileBrowserScreen::class),
        ]);
    }
}
