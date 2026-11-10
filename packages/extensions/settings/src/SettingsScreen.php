<?php

declare(strict_types=1);

namespace Vanda\Settings;

use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;
use Vanda\Actions\Button;
use Vanda\Attributes\Action;
use Vanda\Navigation\Crumb;
use Vanda\Screen\Result;
use Vanda\Screen\Screen;
use Vanda\Screen\ScreenRegistry;
use Vanda\VandaException;

/**
 * A page of settings. Its public Settings properties are read on the first visit and written by
 * «Сохранить» (⌘S); the rest is an ordinary screen: a layout, rules, #[Can]. It is registered
 * with SettingsExtension::page() and opened from the «Настройки» page.
 *
 *     #[Can('settings.shop')]
 *     final class ShopSettingsScreen extends SettingsScreen
 *     {
 *         public ShopSettings $shop;
 *
 *         public function title(): string { return __('Shop'); }
 *         public function description(): string { return __('Name, contacts and maintenance'); }
 *
 *         public function layout(): iterable
 *         {
 *             return [
 *                 Layout::section(__('Shop'))->schema([
 *                     Input::make('shop.name')->label(__('Name')),
 *                     Switcher::make('shop.maintenance')->label(__('Maintenance mode')),
 *                 ]),
 *             ];
 *         }
 *     }
 *
 * title() and description() are plain text: the «Настройки» page shows them without loading the
 * page. A page takes no route parameters. To do more on saving, override save() with #[Action]
 * and call parent::save().
 */
abstract class SettingsScreen extends Screen
{
    public function query(): array
    {
        foreach (self::settingsOf(static::class) as $property => $class) {
            (new ReflectionProperty($this, $property))->setValue($this, $class::get());
        }

        return [];
    }

    public function commandBar(): iterable
    {
        return [
            Button::make((string) __('vanda-settings::settings.save'))->icon('save')->primary()->hotkey('mod+s')->action($this->save(...)),
        ];
    }

    public function breadcrumbs(): iterable
    {
        $index = array_filter(
            app(ScreenRegistry::class)->all(),
            static fn (array $entry): bool => $entry['screen'] === SettingsIndexScreen::class,
        );

        return [
            ...($index === [] ? [] : [Crumb::make((string) __('vanda-settings::settings.title'))->to(SettingsIndexScreen::class)]),
            Crumb::make((string) $this->title()),
        ];
    }

    #[Action]
    public function save(): Result
    {
        foreach (self::settingsOf(static::class) as $property => $class) {
            $settings = (new ReflectionProperty($this, $property))->getValue($this);
            assert($settings instanceof Settings);
            $settings->save();
        }

        return Result::toast((string) __('vanda-settings::settings.saved'));
    }

    /**
     * Public properties of a page typed with a Settings class: name => class.
     *
     * @param class-string<self> $screen
     * @return array<string, class-string<Settings>>
     */
    final public static function settingsOf(string $screen): array
    {
        $found = [];

        foreach ((new ReflectionClass($screen))->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $type = $property->getType();
            if (! $property->isStatic() && $type instanceof ReflectionNamedType && is_subclass_of($type->getName(), Settings::class)) {
                $found[$property->getName()] = $type->getName();
            }
        }

        if ($found === []) {
            throw new VandaException(sprintf(
                '%s extends SettingsScreen but has no public property typed with a Settings class, so it has nothing to read and save. '
                .'Add one and bind the fields to it: public ShopSettings $shop; … Input::make(\'shop.name\').',
                $screen,
            ));
        }

        return $found;
    }
}
