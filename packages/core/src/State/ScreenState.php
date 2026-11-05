<?php

declare(strict_types=1);

namespace Vanda\State;

use Illuminate\Database\Eloquent\Model;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;
use Vanda\Attributes\Locked;
use Vanda\Screen\Screen;
use Vanda\VandaException;

/**
 * Reads and writes the public typed properties of a screen.
 *
 * "Client" state is the part the browser may edit: every public property that
 * is not locked. Models, #[Locked] and (PHP 8.4) private(set) properties are locked.
 */
final class ScreenState
{
    /** @var array<class-string, list<ReflectionProperty>> */
    private static array $properties = [];

    /**
     * @return list<ReflectionProperty>
     */
    public static function properties(Screen $screen): array
    {
        return self::$properties[$screen::class] ??= array_values(array_filter(
            (new ReflectionClass($screen))->getProperties(ReflectionProperty::IS_PUBLIC),
            static fn (ReflectionProperty $property) => ! $property->isStatic() && ! $property->isReadOnly(),
        ));
    }

    public static function isLocked(ReflectionProperty $property): bool
    {
        if ($property->getAttributes(Locked::class) !== []) {
            return true;
        }

        if (method_exists($property, 'isPrivateSet') && ($property->isPrivateSet() || $property->isProtectedSet())) {
            return true;
        }

        $type = $property->getType();

        return $type instanceof ReflectionNamedType && is_subclass_of($type->getName(), Model::class);
    }

    public static function isClientProperty(Screen $screen, string $name): bool
    {
        foreach (self::properties($screen) as $property) {
            if ($property->getName() === $name) {
                return ! self::isLocked($property);
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    public static function dehydrate(Screen $screen, bool $clientOnly = false, bool $secrets = false): array
    {
        $state = [];

        foreach (self::properties($screen) as $property) {
            if (! $property->isInitialized($screen) || ($clientOnly && self::isLocked($property))) {
                continue;
            }

            $value = StateCaster::dehydrate($property->getValue($screen), $secrets);

            if ($value === StateCaster::UNSUPPORTED) {
                throw VandaException::unsupportedStateType(
                    $screen::class,
                    $property->getName(),
                    get_debug_type($property->getValue($screen)),
                );
            }

            $state[$property->getName()] = $value;
        }

        return $state;
    }

    /**
     * @param array<string, mixed> $state
     */
    public static function hydrate(Screen $screen, array $state, bool $clientOnly = false): void
    {
        foreach (self::properties($screen) as $property) {
            $name = $property->getName();

            if (! array_key_exists($name, $state) || ($clientOnly && self::isLocked($property))) {
                continue;
            }

            $current = $property->isInitialized($screen) ? $property->getValue($screen) : null;
            $property->setValue($screen, StateCaster::hydrate($property->getType(), $state[$name], $current));
        }
    }
}
