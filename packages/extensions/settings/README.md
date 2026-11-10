# vanda/settings

Settings for [Vanda](https://github.com/Vanda-Platform/vanda) panels: typed values of the application
kept in the database (`vanda_settings`), read anywhere with `SiteSettings::get()`, and edited on a
«Settings» page of the panel with a page for each group. `#[Secret]` values are encrypted and never
sent to the browser; every change goes into Vanda's activity log value by value.

> This repository is a read-only copy of `packages/extensions/settings` from
> [Vanda-Platform/vanda](https://github.com/Vanda-Platform/vanda). Open issues and pull requests there.

## Install

It needs `vanda/core` 0.4.

```bash
composer require vanda/settings
php artisan migrate
```

## Use

```php
final class SiteSettings extends Settings
{
    #[Validate('required|max:120')]
    public string $name = 'My site';

    public bool $maintenance = false;
}

#[Can('platform.settings.site')]
final class SiteSettingsScreen extends SettingsScreen
{
    public SiteSettings $site;

    public function title(): string { return 'Site'; }

    public function layout(): iterable
    {
        return [Layout::section('Site')->schema([
            Input::make('site.name')->label('Name')->required(),
            Switcher::make('site.maintenance')->label('Maintenance mode'),
        ])];
    }
}

// a service provider
Panel::extension(SettingsExtension::make()->page('site', SiteSettingsScreen::class));

// routes/web.php
Route::vanda('admin', fn () => Route::extension(SettingsExtension::class));   // /admin/settings
```

The guide is the «Settings» page of Vanda's documentation (Extensions → Settings). Tests run in the
monorepo: `composer install && vendor/bin/pest` in `packages/extensions/settings` (the core comes from
`../../core` through a path repository).
