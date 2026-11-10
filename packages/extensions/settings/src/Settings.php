<?php

declare(strict_types=1);

namespace Vanda\Settings;

use BackedEnum;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;
use Vanda\Attributes\Secret;
use Vanda\Form\Form;

/**
 * Values of the application kept in the database and typed like a form: the defaults of the
 * properties hold until someone saves others. Fields bind to it as to any form, rules come from
 * #[Validate] and rules(), and a SettingsScreen edits it.
 *
 *     final class ShopSettings extends Settings
 *     {
 *         #[Validate('required|max:120')]
 *         public string $name = 'Vanda Shop';
 *
 *         public bool $maintenance = false;
 *
 *         #[Secret]
 *         public string $paymentKey = '';   // encrypted in the database, never sent to the browser
 *     }
 *
 *     ShopSettings::get()->maintenance;   // anywhere: the first call of a request reads every row at once
 *
 * A stored value the property no longer accepts (an enum case that was removed, another type)
 * gives way to the default instead of failing the page.
 */
abstract class Settings extends Form
{
    /**
     * Name of the class's row in vanda_settings: "shop" for ShopSettings. Override it before
     * renaming the class, or the saved values stay under the old name.
     */
    public static function key(): string
    {
        $name = (string) preg_replace('/Settings$/', '', class_basename(static::class));

        return Str::kebab($name !== '' ? $name : class_basename(static::class));
    }

    /**
     * The saved values over the defaults. Every call gives a new object: changing it changes
     * nothing until save().
     */
    public static function get(): static
    {
        $settings = app(SettingsStore::class)->get(static::class);
        assert($settings instanceof static);

        return $settings;
    }

    /**
     * Writes the values. An empty #[Secret] value keeps the saved one, so a form that never shows
     * the key does not erase it.
     */
    public function save(): void
    {
        app(SettingsStore::class)->save($this);
    }

    /**
     * Properties typed with a backed enum: the activity log keeps their values by label().
     *
     * @internal
     *
     * @return array<string, class-string<BackedEnum>>
     */
    final public static function enums(): array
    {
        $enums = [];

        foreach ((new ReflectionClass(static::class))->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $type = $property->getType();
            if (! $property->isStatic() && $type instanceof ReflectionNamedType && is_subclass_of($type->getName(), BackedEnum::class)) {
                $enums[$property->getName()] = $type->getName();
            }
        }

        return $enums;
    }

    /**
     * Properties with #[Secret]: encrypted in the database, left out of the page and the snapshot.
     *
     * @internal
     *
     * @return list<string>
     */
    final public static function secrets(): array
    {
        $names = [];

        foreach ((new ReflectionClass(static::class))->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            if (! $property->isStatic() && $property->getAttributes(Secret::class) !== []) {
                $names[] = $property->getName();
            }
        }

        return $names;
    }
}
