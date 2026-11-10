<?php

declare(strict_types=1);

namespace Vanda\Extensions;

use Vanda\Access\PermissionGroup;
use Vanda\Navigation\SearchResult;

/**
 * A package that adds to the panel: screens under a prefix of its own, permissions for the role
 * form, records for ⌘K. The package registers it in its service provider, the application mounts
 * its screens inside Route::vanda():
 *
 *     final class SettingsExtension extends Extension
 *     {
 *         public function name(): string { return 'settings'; }
 *
 *         public function routes(): void
 *         {
 *             Route::screen('/', SettingsScreen::class)->name('index');   // /admin/settings, "admin.settings.index"
 *         }
 *
 *         public function permissions(): iterable
 *         {
 *             return [PermissionGroup::make(__('Settings'))->add('platform.settings', __('Panel settings'))];
 *         }
 *     }
 *
 *     Panel::extension(new SettingsExtension);          // boot() of the package's service provider
 *
 *     Route::vanda('admin', function () {               // the application's routes file
 *         Route::extension(SettingsExtension::class);
 *     });
 *
 * Registering the same class again replaces the first instance, so the application configures an
 * extension its package registered by registering it once more in its own boot().
 */
abstract class Extension
{
    /**
     * The extension's name in URLs and route names: lowercase latin letters, digits and dashes
     * ("settings", "two-factor"). Two extensions of a panel cannot share it.
     */
    abstract public function name(): string;

    /**
     * The extension's screens, declared with Route::screen() as in a routes file. Route::extension()
     * calls it inside Route::vanda(), under the extension's name: "/" is /admin/settings, and a
     * route named "index" is "admin.settings.index". Leave out names you never link to by name.
     */
    public function routes(): void {}

    /**
     * Permissions of the extension, shown in the role and user forms like the application's own:
     *
     *     return [PermissionGroup::make(__('Settings'))->add('platform.settings', __('Panel settings'))];
     *
     * @return iterable<PermissionGroup>
     */
    public function permissions(): iterable
    {
        return [];
    }

    /**
     * Records the ⌘K palette can find, as with Panel::search(); results that lead to screens the
     * user may not open are hidden by themselves. Only an extension that overrides it adds a provider.
     *
     * @return iterable<SearchResult>
     */
    public function search(string $query): iterable
    {
        return [];
    }

    /**
     * The instance registered with Panel::extension(): the application's when it replaced the
     * package's. Screens of the extension read their settings from it:
     *
     *     SettingsExtension::registered()->pages()
     */
    final public static function registered(): static
    {
        $extension = app(ExtensionRegistry::class)->get(static::class);
        assert($extension instanceof static);

        return $extension;
    }
}
