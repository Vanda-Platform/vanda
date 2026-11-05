<?php

declare(strict_types=1);

namespace Vanda\State;

use BackedEnum;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use ReflectionNamedType;
use ReflectionType;
use Vanda\Form\Form;

/**
 * Converts typed PHP values to JSON-safe data and back, using the declared type.
 */
final class StateCaster
{
    public const UNSUPPORTED = "\0unsupported";

    public static function dehydrate(mixed $value): mixed
    {
        return match (true) {
            $value === null, is_scalar($value) => $value,
            $value instanceof Form => $value->toArray(),
            $value instanceof Model => ['__model' => $value::class, 'key' => $value->exists ? $value->getKey() : null],
            $value instanceof BackedEnum => $value->value,
            $value instanceof DateTimeInterface => Carbon::instance($value)->toIso8601String(),
            $value instanceof Collection => array_map(self::dehydrate(...), $value->all()),
            is_array($value) => array_map(self::dehydrate(...), $value),
            default => self::UNSUPPORTED,
        };
    }

    public static function hydrate(?ReflectionType $type, mixed $value, mixed $current = null): mixed
    {
        if (! $type instanceof ReflectionNamedType) {
            return $value;
        }

        if ($value === null) {
            return $type->allowsNull() ? null : $current;
        }

        $name = $type->getName();

        if (is_object($value) && $value instanceof $name) {
            return $value;
        }

        return match (true) {
            $name === 'int' => is_numeric($value) ? (int) $value : ($type->allowsNull() && $value === '' ? null : $current),
            $name === 'float' => is_numeric($value) ? (float) $value : ($type->allowsNull() && $value === '' ? null : $current),
            $name === 'bool' => filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? (bool) $value,
            $name === 'string' => is_scalar($value) ? (string) $value : $current,
            $name === 'array' => (array) $value,
            $name === 'mixed' => $value,
            is_subclass_of($name, Form::class) => $name::fromArray((array) $value),
            is_subclass_of($name, Model::class) => self::model($name, $value, $current),
            is_subclass_of($name, BackedEnum::class) => $name::tryFrom($value),
            is_a($name, DateTimeInterface::class, true) => Carbon::parse((string) $value),
            is_a($name, Collection::class, true) => new Collection((array) $value),
            default => $value,
        };
    }

    /**
     * @param class-string<Model> $class
     */
    private static function model(string $class, mixed $value, mixed $current): ?Model
    {
        $key = is_array($value) ? ($value['key'] ?? null) : $value;

        if ($current instanceof Model && $current->exists && $key !== null && (string) $current->getKey() === (string) $key) {
            return $current;
        }

        return $key === null ? new $class : $class::query()->findOrFail($key);
    }
}
