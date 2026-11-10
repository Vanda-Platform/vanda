<?php

declare(strict_types=1);

namespace Vanda\Settings;

use Illuminate\Support\Facades\Route;
use ReflectionClass;
use Vanda\Access\PermissionGroup;
use Vanda\Attributes\Can;
use Vanda\Extensions\Extension;
use Vanda\VandaException;

/**
 * Settings pages in the panel: «Настройки» at /admin/settings with the pages the user may open,
 * each page at /admin/settings/<slug>. The package registers it with no pages; the application
 * registers it again with its own in a service provider and mounts it in its routes:
 *
 *     Panel::extension(SettingsExtension::make()
 *         ->page('shop', ShopSettingsScreen::class)
 *         ->page('mail', MailSettingsScreen::class));
 *
 *     Route::vanda('admin', function () {
 *         Route::extension(SettingsExtension::class);   // "admin.settings.index", "admin.settings.shop", …
 *     });
 *
 *     MenuItem::make(__('Settings'))->icon('settings')->to(SettingsIndexScreen::class);
 *
 * The abilities the pages' #[Can] ask for appear in the role form under «Настройки», named by
 * the pages; do not declare them in Panel::permissions() too.
 */
final class SettingsExtension extends Extension
{
    /** @var array<string, class-string<SettingsScreen>> slug => page */
    private array $pages = [];

    public static function make(): self
    {
        return new self;
    }

    /**
     * A page of settings at settings/<slug>, listed on «Настройки» in the order of the calls.
     *
     * @param class-string<SettingsScreen> $screen
     */
    public function page(string $slug, string $screen): self
    {
        if (! is_subclass_of($screen, SettingsScreen::class)) {
            throw new VandaException(sprintf(
                'SettingsExtension::page(\'%s\', %s::class): %s does not extend Vanda\Settings\SettingsScreen. A settings page is a SettingsScreen with a public Settings property.',
                $slug,
                class_basename($screen),
                $screen,
            ));
        }
        if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) !== 1) {
            throw new VandaException(sprintf(
                'SettingsExtension::page(\'%s\', …): the slug is a part of the address and a route name, so it takes lowercase latin letters, digits and dashes, e.g. \'mail\' or \'payment-methods\'.',
                $slug,
            ));
        }

        SettingsScreen::settingsOf($screen);   // fails now, not on the first visit, when the page has nothing to save
        $this->pages[$slug] = $screen;

        return $this;
    }

    /**
     * @return array<string, class-string<SettingsScreen>> slug => page
     */
    public function pages(): array
    {
        return $this->pages;
    }

    /**
     * The page that edits the Settings class with this key(), for the activity log's link.
     *
     * @return class-string<SettingsScreen>|null
     */
    public function pageOf(string $key): ?string
    {
        foreach ($this->pages as $page) {
            foreach (SettingsScreen::settingsOf($page) as $class) {
                if ($class::key() === $key) {
                    return $page;
                }
            }
        }

        return null;
    }

    /**
     * The title of a page as «Настройки» shows it.
     *
     * @param class-string<SettingsScreen> $page
     */
    public static function titleOf(string $page): string
    {
        return (string) app($page)->title();
    }

    public function name(): string
    {
        return 'settings';
    }

    public function routes(): void
    {
        Route::screen('/', SettingsIndexScreen::class)->name('index');

        foreach ($this->pages as $slug => $page) {
            Route::screen($slug, $page)->name($slug);
        }
    }

    public function permissions(): iterable
    {
        /** @var array<string, list<string>> $abilities ability => titles of the pages that ask for it */
        $abilities = [];

        foreach ($this->pages as $page) {
            $can = (new ReflectionClass($page))->getAttributes(Can::class)[0] ?? null;
            if ($can !== null) {
                $abilities[$can->newInstance()->ability][] = self::titleOf($page);
            }
        }

        if ($abilities === []) {
            return [];
        }

        $group = PermissionGroup::make((string) __('vanda-settings::settings.title'));
        foreach ($abilities as $ability => $titles) {
            $group->add($ability, implode(', ', array_unique($titles)));
        }

        return [$group];
    }
}
