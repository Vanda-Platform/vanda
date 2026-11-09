# Vanda

An admin panel for Laravel built from screens. Every page is a PHP class; the server sends a typed
description of the page and Vanda's prebuilt React renderer draws it. You write only PHP: no Blade,
no JavaScript, no build step. Tables with filters and views, trees, forms with dependent fields,
uploads with cropping, rich text, maps, charts, ⌘K search, permissions, an activity log, phone
layout, keyboard and screen-reader support come with it.

> This repository is a read-only copy of `packages/core` from
> [Vanda-Platform/vanda](https://github.com/Vanda-Platform/vanda). Open issues and pull requests there.

## Requirements

PHP 8.3 or newer, Laravel 12 or 13.

## Install

```bash
composer require vanda/core
php artisan migrate
```

Give your `User` model Vanda's permissions:

```php
use Vanda\Access\HasPermissions;

class User extends Authenticatable
{
    use HasFactory, HasPermissions, Notifiable;
}
```

Write a screen, for example `app/Vanda/UserListScreen.php`:

```php
namespace App\Vanda;

use App\Models\User;
use Vanda\Attributes\Can;
use Vanda\Layouts\Layout;
use Vanda\Layouts\TD;
use Vanda\Screen\Screen;

#[Can('platform.users')]
final class UserListScreen extends Screen
{
    public function title(): string
    {
        return __('Users');
    }

    public function layout(): iterable
    {
        return [
            Layout::table()
                ->query(fn () => User::query())
                ->searchable(['name', 'email'])
                ->columns([
                    TD::make('name', __('Name'))->sortable(),
                    TD::make('email', __('Email')),
                ]),
        ];
    }
}
```

Route it in `routes/web.php` (sign-in and sign-out come with `Route::vanda()`):

```php
Route::vanda('admin', function () {
    Route::screen('users', UserListScreen::class)->name('admin.users');
});
```

Describe the permissions and the menu in `AppServiceProvider::boot()`:

```php
Panel::permissions(fn () => [PermissionGroup::make(__('Access'))->add('platform.users', __('Users'))]);

Panel::menu(fn () => [
    MenuItem::make(__('Users'))->icon('users')->to(UserListScreen::class),
]);
```

Create someone with every permission and sign in at `/admin/login`; the panel opens its first menu
item (or `config('vanda.home')`):

```bash
php artisan vanda:admin admin@example.com
```

`php artisan vendor:publish --tag=vanda-config` publishes `config/vanda.php` (brand colour, density,
the sign-in picture, the activity log, uploads, maps).

## For AI agents

Vanda ships rules for coding agents. With [Laravel Boost](https://laravel.com/docs/boost),
`php artisan boost:install` adds them to the project: short guidelines for every prompt and the
`vanda-screens` skill with the full guide to writing screens. Then:

```bash
php artisan vanda:check --json                 # build every screen; problems with file:line, exit 1 if any
php artisan vanda:inspect /admin/users         # the node tree of a screen, with the PHP line of every node
```

In development every page is checked for field paths, modals and icons that do not exist, and the
error says what was meant. Screens are tested without a browser:

```php
Panel::test(UserEditScreen::class, ['user' => $user])
    ->set('form.email', 'anna@example.com')
    ->call('save')
    ->assertToast('Saved');
```

## License

MIT. See [LICENSE](LICENSE).
