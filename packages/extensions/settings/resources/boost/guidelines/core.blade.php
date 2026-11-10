## Vanda settings

`vanda/settings` keeps values of the application in the database (table `vanda_settings`) and edits them on settings pages of the Vanda panel. Never add a settings table, a key-value model or a config file written at runtime of your own.

### Rules

- A group of settings is a class that extends `Vanda\Settings\Settings` (a `Vanda\Form\Form`): public typed properties with defaults, rules in `#[Validate]` or `rules()`. Read it anywhere with `ShopSettings::get()->name`; the first call of a request reads every row with one query. Each call gives a new object, so changing it does nothing until `->save()`.
- Keys, tokens and passwords take `#[Secret]`: they are encrypted, never sent to the browser, and an empty value on saving keeps the saved one.
- A page of settings extends `Vanda\Settings\SettingsScreen`, has a public property typed with the settings class and binds the fields to it (`Input::make('shop.name')`). It loads and saves by itself, with «Save» (⌘S) in its command bar; do not write `query()` or a save action for it. Guard it with `#[Can('settings.shop')]`: the ability appears in the role form under «Settings» named by the page, so do not declare it in `Panel::permissions()`. `title()` and `description()` are plain text.
- Register the pages in a service provider, mount them inside `Route::vanda()` and link to the list from the menu:

@verbatim
<code-snippet name="Settings pages in the panel" lang="php">
// AppServiceProvider::boot()
Panel::extension(SettingsExtension::make()
    ->page('shop', ShopSettingsScreen::class)       // /admin/settings/shop
    ->page('mail', MailSettingsScreen::class));

// routes/web.php
Route::vanda('admin', function () {
    Route::extension(SettingsExtension::class);     // /admin/settings: the pages the user may open
});

// the menu
MenuItem::make(__('Settings'))->icon('settings')->to(SettingsIndexScreen::class);
</code-snippet>
@endverbatim

@verbatim
<code-snippet name="A settings class and its page" lang="php">
final class ShopSettings extends Settings
{
    #[Validate('required|max:120')]
    public string $name = 'Shop';

    public bool $maintenance = false;

    #[Secret]
    public string $paymentKey = '';
}

#[Can('settings.shop')]
final class ShopSettingsScreen extends SettingsScreen
{
    public ShopSettings $shop;

    public function title(): string { return __('Shop'); }
    public function description(): string { return __('Name, maintenance and payments'); }

    public function layout(): iterable
    {
        return [
            Layout::section(__('Shop'))->schema([
                Input::make('shop.name')->label(__('Name'))->required(),
                Switcher::make('shop.maintenance')->label(__('Maintenance mode')),
                Password::make('shop.paymentKey')->label(__('Payment key'))->help(__('Leave it empty to keep the saved key')),
            ]),
        ];
    }
}
</code-snippet>
@endverbatim

- Test a page like any screen: `Panel::test(ShopSettingsScreen::class)->set('shop.name', 'Lavka')->call('save')->assertToast('Settings saved');` then `ShopSettings::get()->name`.
- Renaming a settings class moves its values to another row: override `key()` with the old name (`return 'shop';`) first.
