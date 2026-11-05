<?php

declare(strict_types=1);

namespace Vanda\Screen;

use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use ReflectionMethod;
use ReflectionNamedType;
use Vanda\Attributes\Action;
use Vanda\Support\Signer;

/**
 * Rules for calling #[Action] methods from the browser.
 *
 * Every rendered button carries a token that signs the screen, the method and
 * its arguments, so a request can only call what was actually rendered.
 */
final class ActionCall
{
    public static function attribute(string $class, string $method): ?Action
    {
        if (! method_exists($class, $method)) {
            return null;
        }

        $reflection = new ReflectionMethod($class, $method);

        if (! $reflection->isPublic() || $reflection->isStatic()) {
            return null;
        }

        $attributes = $reflection->getAttributes(Action::class);

        return $attributes === [] ? null : $attributes[0]->newInstance();
    }

    public static function isAction(string $class, string $method): bool
    {
        return self::attribute($class, $method) !== null;
    }

    /**
     * Models become their route keys, enums their values.
     *
     * @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    public static function normalizeArguments(array $arguments): array
    {
        return array_map(static fn (mixed $value) => match (true) {
            $value instanceof Model => $value->getRouteKey(),
            $value instanceof BackedEnum => $value->value,
            is_array($value) => self::normalizeArguments($value),
            default => $value,
        }, $arguments);
    }

    /**
     * @param array<string, mixed> $arguments
     */
    public static function sign(string $screen, string $method, array $arguments): string
    {
        return Signer::sign(self::payload($screen, $method, $arguments));
    }

    /**
     * @param array<string, mixed> $arguments
     */
    public static function verify(string $screen, string $method, array $arguments, string $token): bool
    {
        return Signer::verify(self::payload($screen, $method, $arguments), $token);
    }

    /**
     * Turns stored arguments back into method parameters; models are resolved
     * through their route binding.
     *
     * @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    public static function bind(object $screen, string $method, array $arguments): array
    {
        $bound = [];

        foreach ((new ReflectionMethod($screen, $method))->getParameters() as $parameter) {
            $name = $parameter->getName();

            if (! array_key_exists($name, $arguments)) {
                continue;
            }

            $type = $parameter->getType();
            $value = $arguments[$name];

            if ($type instanceof ReflectionNamedType && is_subclass_of($type->getName(), Model::class) && ! $value instanceof Model) {
                $model = $type->getName();
                $value = (new $model)->resolveRouteBinding($value) ?? abort(404);
            } elseif ($type instanceof ReflectionNamedType && is_subclass_of($type->getName(), BackedEnum::class)) {
                $value = $type->getName()::from($value);
            }

            $bound[$name] = $value;
        }

        return $bound;
    }

    /**
     * @param array<string, mixed> $arguments
     */
    private static function payload(string $screen, string $method, array $arguments): string
    {
        ksort($arguments);

        return $screen.'@'.$method.':'.json_encode($arguments, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }
}
