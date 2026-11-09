<?php

declare(strict_types=1);

namespace Vanda\Access;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use ReflectionClass;
use ReflectionMethod;
use Vanda\Attributes\Can;
use Vanda\Screen\Screen;

/**
 * Applies #[Can] to screens and actions, and answers "may the user see this?"
 * for buttons and menu items so that forbidden things are not even shown.
 */
final class Authorizer
{
    /**
     * @internal vanda:check builds every screen for someone who may do everything, so the parts
     * some roles never see are checked too. Never set it in a request.
     */
    public static bool $allowAll = false;

    /**
     * Abilities that need no model can be checked before the screen loads anything.
     *
     * @param class-string $screen
     */
    public static function allowsScreenClass(string $screen): bool
    {
        $can = self::attribute(new ReflectionClass($screen));

        return self::$allowAll || $can === null || $can->model !== null || Gate::allows($can->ability);
    }

    public static function authorizeScreen(Screen $screen): void
    {
        $can = self::attribute(new ReflectionClass($screen));

        if ($can !== null && ! self::allows($can, self::modelFromScreen($screen, $can))) {
            throw new AuthorizationException(sprintf('This action is unauthorized (%s requires "%s").', class_basename($screen), $can->ability));
        }
    }

    /**
     * @param array<string, mixed> $arguments raw or bound action arguments
     */
    public static function allowsAction(Screen $screen, string $method, array $arguments = []): bool
    {
        if (! method_exists($screen, $method)) {
            return false;
        }

        $can = self::attribute(new ReflectionMethod($screen, $method));

        if ($can === null) {
            return true;
        }

        $model = $can->model !== null && array_key_exists($can->model, $arguments)
            ? $arguments[$can->model]
            : self::modelFromScreen($screen, $can);

        return self::allows($can, $model);
    }

    /**
     * @param array<string, mixed> $arguments
     */
    public static function authorizeAction(Screen $screen, string $method, array $arguments): void
    {
        if (! self::allowsAction($screen, $method, $arguments)) {
            throw new AuthorizationException(sprintf('This action is unauthorized (%s::%s()).', class_basename($screen), $method));
        }
    }

    private static function allows(Can $can, mixed $model): bool
    {
        if (self::$allowAll) {
            return true;
        }

        return $model === null ? Gate::allows($can->ability) : Gate::allows($can->ability, [$model]);
    }

    private static function modelFromScreen(Screen $screen, Can $can): mixed
    {
        if ($can->model === null || ! property_exists($screen, $can->model)) {
            return null;
        }

        return (new \ReflectionProperty($screen, $can->model))->isInitialized($screen) ? $screen->{$can->model} : null;
    }

    private static function attribute(ReflectionClass|ReflectionMethod $reflection): ?Can
    {
        $attributes = $reflection->getAttributes(Can::class);

        return $attributes === [] ? null : $attributes[0]->newInstance();
    }
}
