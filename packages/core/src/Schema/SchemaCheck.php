<?php

declare(strict_types=1);

namespace Vanda\Schema;

use Vanda\Screen\Screen;
use Vanda\Support\Icons;
use Vanda\Support\Suggest;
use Vanda\VandaException;

/**
 * Checks a page before it leaves the server for references that would fail quietly in the
 * browser: a field bound to a path the screen's state does not have, a visibleWhen / requiredWhen /
 * disabledWhen / computed or dependsOn that reads such a path, a ModalToggle for a modal the screen
 * does not have, an icon the renderer does not draw. Every problem names the node, its file:line
 * and what was probably meant, so the fix needs no reading of Vanda's source.
 *
 * On in development and tests (and always in `vanda:inspect`); production pages are not checked.
 *
 *     SchemaCheck::problems($screen, $runtime->ir());
 *     // ['input "Email" at app/Vanda/Screens/UserEditScreen.php:47 is bound to "form.emial", but UserForm has no property "emial". Did you mean "form.email"? …']
 */
final class SchemaCheck
{
    /** Set by the service provider (development) and the test case: check every page built. */
    public static bool $enabled = false;

    /** Expressions a node may carry, as the renderer receives them. */
    private const EXPRESSIONS = ['visibleWhen' => 'visibleWhen', 'requiredWhen' => 'requiredWhen', 'disabledWhen' => 'disabledWhen', 'computed' => 'computed'];

    private const NO_ICON = 'which the renderer does not draw';

    /** Nodes whose `fields` are row templates with paths relative to the row, not paths of the state. */
    private const TEMPLATES = ['repeater', 'matrix'];

    /**
     * @param array<string, mixed> $ir
     */
    public static function assert(Screen $screen, array $ir): void
    {
        $problems = self::problems($screen, $ir);

        if ($problems !== []) {
            $icons = array_filter($problems, static fn (string $problem) => str_contains($problem, self::NO_ICON));
            throw VandaException::schemaProblems($screen::class, $problems, $icons === [] ? null : 'Icons the renderer draws: '.implode(', ', Icons::names()).'.');
        }
    }

    /**
     * @param array<string, mixed> $ir
     * @return list<string>
     */
    public static function problems(Screen $screen, array $ir): array
    {
        $nodes = [];
        foreach ([$ir['badge'] ?? null, ...($ir['commandBar'] ?? []), ...($ir['layout'] ?? [])] as $node) {
            self::collect($node, $nodes);
        }

        $modals = array_values(array_map(
            static fn (array $node): string => (string) $node['id'],
            array_filter($nodes, static fn (array $node): bool => $node['type'] === 'modal'),
        ));

        $problems = [];

        foreach ($nodes as $node) {
            $where = self::where($node);

            if (is_string($node['path'] ?? null)) {
                $problem = self::path($screen, $node['path']);
                if ($problem !== null) {
                    $problems[] = sprintf('%s is bound to "%s", %s', $where, $node['path'], $problem);
                }
            }

            foreach (self::EXPRESSIONS as $key => $method) {
                foreach (self::fieldsOf($node[$key] ?? null) as $path) {
                    $problem = self::path($screen, $path);
                    if ($problem !== null) {
                        $problems[] = sprintf('%s: its ->%s() reads "%s", %s', $where, $method, $path, $problem);
                    }
                }
            }

            foreach (is_array($node['dependsOn'] ?? null) ? $node['dependsOn'] : [] as $pattern) {
                $problem = is_string($pattern) ? self::path($screen, $pattern) : null;
                if ($problem !== null) {
                    $problems[] = sprintf('%s: its ->dependsOn(\'%s\') waits for a path that never changes, %s', $where, $pattern, $problem);
                }
            }

            if ($node['type'] === 'modaltoggle' && is_string($node['modal'] ?? null) && ! in_array($node['modal'], $modals, true)) {
                $problems[] = sprintf('%s opens the modal "%s", but this screen has no Layout::modal(\'%s\', …).%s', $where, $node['modal'], $node['modal'], self::modalHint($node['modal'], $modals));
            }

            if (is_string($node['icon'] ?? null) && ! Icons::exists($node['icon'])) {
                $problems[] = sprintf('%s has the icon "%s", %s.%s', $where, $node['icon'], self::NO_ICON, self::iconHint($node['icon']));
            }
        }

        foreach (is_array($ir['app']['menu'] ?? null) ? $ir['app']['menu'] : [] as $item) {
            if (is_array($item) && is_string($item['icon'] ?? null) && ! Icons::exists($item['icon'])) {
                $problems[] = sprintf('Menu item "%s" has the icon "%s", %s.%s', $item['label'] ?? '', $item['icon'], self::NO_ICON, self::iconHint($item['icon']));
            }
        }

        return array_values(array_unique($problems));
    }

    /**
     * Every node of the page (anything with a type and an id), row templates left out.
     *
     * @param list<array<string, mixed>> $nodes
     */
    private static function collect(mixed $value, array &$nodes): void
    {
        if (! is_array($value)) {
            return;
        }

        $isNode = is_string($value['type'] ?? null) && is_string($value['id'] ?? null);
        if ($isNode) {
            $nodes[] = $value;
        }

        foreach ($value as $key => $child) {
            if ($isNode && $key === 'fields' && in_array($value['type'], self::TEMPLATES, true)) {
                continue;
            }
            if (in_array($key, self::EXPRESSIONS, true) || $key === 'state') {
                continue;
            }
            self::collect($child, $nodes);
        }
    }

    /** "input "Email" at app/…/UserEditScreen.php:47", or the node's id when no source line is known. */
    private static function where(array $node): string
    {
        $name = $node['label'] ?? $node['title'] ?? null;
        $what = $node['type'].(is_string($name) && $name !== '' ? sprintf(' "%s"', $name) : '');

        return is_string($node['source'] ?? null) ? "{$what} at {$node['source']}" : "{$what} (node \"{$node['id']}\")";
    }

    /** Why a path is not in the state and what was meant; null when it is fine. */
    private static function path(Screen $screen, string $path): ?string
    {
        $broken = StatePaths::broken($screen, $path);
        if ($broken === null) {
            return null;
        }

        if ($broken['leaf']) {
            return sprintf('but "%s" is a single value (%s) and has nothing inside it.', $broken['at'], $broken['owner']);
        }

        $prefix = $broken['at'] === '' ? '' : $broken['at'].'.';
        $rest = substr($path, strlen($prefix.$broken['name']));
        $closest = Suggest::closest($broken['name'], $broken['known']);
        $meant = $closest !== null ? sprintf(' Did you mean "%s"?', $prefix.$closest.$rest) : '';
        $known = $broken['known'] === [] ? 'nothing public' : implode(', ', $broken['known']);

        if ($broken['at'] === '') {
            return sprintf(
                'but %s has no public property "%s".%s Its public properties: %s. Fields read and write public properties of the screen or of a Form in it (Input::make(\'form.email\') for public UserForm $form).',
                $broken['owner'],
                $broken['name'],
                $meant,
                $known,
            );
        }

        return sprintf('but %s has no property "%s".%s %s has: %s.', $broken['owner'], $broken['name'], $meant, $broken['owner'], $known);
    }

    /**
     * @param list<string> $modals
     */
    private static function modalHint(string $modal, array $modals): string
    {
        if ($modals === []) {
            return sprintf(' Add Layout::modal(\'%s\', \'Title\')->schema([…]) to layout().', $modal);
        }

        $closest = Suggest::closest($modal, $modals);

        return ($closest !== null ? sprintf(' Did you mean "%s"?', $closest) : '').' Modals on this screen: '.implode(', ', $modals).'.';
    }

    private static function iconHint(string $icon): string
    {
        $closest = Icons::closest($icon);

        return $closest !== null ? sprintf(' Did you mean "%s"?', $closest) : ' Pick one from the list below.';
    }

    /**
     * Field paths an expression reads, from its array form ({"field": "form.country"}).
     *
     * @return list<string>
     */
    private static function fieldsOf(mixed $expression): array
    {
        if (! is_array($expression)) {
            return [];
        }
        if (is_string($expression['field'] ?? null)) {
            return [$expression['field']];
        }

        $fields = [];
        foreach ($expression as $part) {
            array_push($fields, ...self::fieldsOf($part));
        }

        return array_values(array_unique($fields));
    }
}
