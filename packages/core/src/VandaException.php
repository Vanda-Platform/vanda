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

    /**
     * The problems SchemaCheck found in a page: each says which node, where, and what was meant.
     *
     * @param list<string> $problems
     * @param string|null $footer what the problems refer to, once (the icon names)
     */
    public static function schemaProblems(string $class, array $problems, ?string $footer = null): self
    {
        $lines = array_map(static fn (int $index, string $problem) => ($index + 1).'. '.$problem, array_keys($problems), $problems);

        return new self(sprintf(
            "%s has %d %s that would fail quietly in the browser (Vanda checks pages in development and tests):\n%s%s",
            $class,
            count($problems),
            count($problems) === 1 ? 'problem' : 'problems',
            implode("\n", $lines),
            $footer === null ? '' : "\n\n".$footer,
        ));
    }

    /**
     * @param list<string> $paths every field path of the screen
     */
    public static function testSetsUnknownPath(string $class, string $path, ?string $closest, array $paths): self
    {
        return new self(sprintf(
            "Panel::test(%s::class)->set('%s', …): no field of the screen edits \"%s\", so the browser could never send it and the test would pass on a value that was ignored.%s Fields of the screen: %s. (To prove the server ignores such a value, send it with ->tamper().)",
            class_basename($class),
            $path,
            $path,
            $closest !== null ? sprintf(' Did you mean "%s"?', $closest) : '',
            $paths === [] ? 'none' : implode(', ', $paths),
        ));
    }

    public static function testSetsReadonlyField(string $class, string $path, string $field): self
    {
        return new self(sprintf(
            "Panel::test(%s::class)->set('%s', …): the field \"%s\" is read-only, disabled or hidden on this screen, so the browser never sends it. Give the value through the screen's data (query() or the record) or make the field editable; to prove the server ignores it, send it with ->tamper().",
            class_basename($class),
            $path,
            $field,
        ));
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
