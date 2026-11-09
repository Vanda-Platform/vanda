<?php

declare(strict_types=1);

namespace Vanda\Schema;

use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;
use Vanda\Form\Form;
use Vanda\Screen\Screen;

/**
 * Where a dot path leads in a screen's state, read from its declared types: a public property of
 * the screen, then the public properties of a Form in it, and so on. Inside an array, a model or
 * an untyped value the shape is not declared, so any path there counts as known.
 *
 *     StatePaths::broken(new UserEditScreen, 'form.emial');
 *     // ['at' => 'form', 'name' => 'emial', 'owner' => 'UserForm', 'known' => ['name', 'email', …]]
 */
final class StatePaths
{
    /**
     * Null when the path exists (or leads where the shape is not declared); else where it breaks:
     * the path before the unknown name, the name, what it was looked up in and what is there.
     * "*" (a dependsOn pattern) matches anything from where it stands.
     *
     * @return array{at: string, name: string, owner: string, known: list<string>, leaf: bool}|null
     */
    public static function broken(Screen $screen, string $path): ?array
    {
        $segments = explode('.', $path);
        $properties = self::properties(new ReflectionClass($screen));
        $owner = class_basename($screen);

        foreach ($segments as $index => $segment) {
            if (str_contains($segment, '*')) {
                return null;
            }

            $at = implode('.', array_slice($segments, 0, $index));
            if (! isset($properties[$segment])) {
                return ['at' => $at, 'name' => $segment, 'owner' => $owner, 'known' => array_keys($properties), 'leaf' => false];
            }

            if ($index === count($segments) - 1) {
                return null;
            }

            $type = $properties[$segment]->getType();
            if (! $type instanceof ReflectionNamedType) {
                return null;
            }

            $class = $type->getName();
            $here = $at === '' ? $segment : "{$at}.{$segment}";

            if ($type->isBuiltin()) {
                if (in_array($class, ['array', 'iterable', 'mixed', 'object'], true)) {
                    return null;
                }

                // A string, a number or a flag has nothing inside it.
                return ['at' => $here, 'name' => $segments[$index + 1], 'owner' => $class, 'known' => [], 'leaf' => true];
            }

            if (enum_exists($class)) {
                return ['at' => $here, 'name' => $segments[$index + 1], 'owner' => class_basename($class), 'known' => [], 'leaf' => true];
            }

            if (! is_subclass_of($class, Form::class)) {
                return null;
            }

            $properties = self::properties(new ReflectionClass($class));
            $owner = class_basename($class);
        }

        return null;
    }

    /**
     * @param ReflectionClass<object> $class
     * @return array<string, ReflectionProperty>
     */
    private static function properties(ReflectionClass $class): array
    {
        $properties = [];
        foreach ($class->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            if (! $property->isStatic()) {
                $properties[$property->getName()] = $property;
            }
        }

        return $properties;
    }
}
