<?php

declare(strict_types=1);

namespace Vanda;

use RuntimeException;

/**
 * Every message says what is wrong, where, and how to fix it, so that a
 * developer (or an AI agent) can act on it without reading Vanda's source.
 */
final class VandaException extends RuntimeException
{
    public static function actionNotMarked(string $class, string $method): self
    {
        return new self(sprintf(
            '%s::%s() is used as a button action but has no #[Action] attribute. '
            .'Add #[Vanda\Attributes\Action] above the method to make it callable from the UI.',
            $class,
            $method,
        ));
    }

    public static function actionTargetNotScreen(string $method): self
    {
        return new self(sprintf(
            'Button action %s() must point to a method of the screen, e.g. ->action($this->%s(...)).',
            $method,
            $method,
        ));
    }

    public static function unknownAction(string $class, string $method): self
    {
        return new self(sprintf('%s has no action named "%s".', $class, $method));
    }

    public static function invalidActionToken(string $class, string $method): self
    {
        return new self(sprintf(
            'The call to %s::%s() was rejected: its arguments do not match the button that was rendered.',
            $class,
            $method,
        ));
    }

    public static function unsupportedStateType(string $class, string $property, string $type): self
    {
        return new self(sprintf(
            'Property %s::$%s of type %s cannot be kept in screen state. '
            .'Use scalars, arrays, enums, dates, Eloquent models or Vanda\Form\Form objects, '
            .'or make the property protected/private if the browser does not need it.',
            $class,
            $property,
            $type,
        ));
    }

    public static function screenNotRouted(string $class): self
    {
        return new self(sprintf(
            '%s has no route. Register it in a routes file: Route::screen(\'some/uri\', %s::class)->name(\'...\');',
            $class,
            class_basename($class),
        ));
    }

    public static function notAScreen(string $class): self
    {
        return new self(sprintf('%s does not extend Vanda\Screen\Screen.', $class));
    }

    public static function duplicateNodeId(string $id, ?string $first, ?string $second): self
    {
        return new self(sprintf(
            'Two schema nodes share the id "%s"%s. Give one of them an explicit ->id(\'...\').',
            $id,
            $first && $second ? " ({$first} and {$second})" : '',
        ));
    }
}
