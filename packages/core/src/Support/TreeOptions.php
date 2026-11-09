<?php

declare(strict_types=1);

namespace Vanda\Support;

use Closure;

/**
 * Options of a tree picker built from nested records (Select::tree(), Filter::select()->tree()):
 * tree order (parents before children), each with its parent key; a branch can be disabled.
 */
final class TreeOptions
{
    /**
     * @param iterable<mixed>|Closure(): iterable<mixed> $items records with key, label and parent key
     * @param int|string|null $excludeBranch this record and everything inside it cannot be chosen
     * @return list<array{value: int|string, label: string, parent?: int|string, disabled?: true}>
     */
    public static function build(iterable|Closure $items, string $label, string $parent, string $key, int|string|null $excludeBranch = null): array
    {
        $items = $items instanceof Closure ? $items() : $items;
        $items = is_array($items) ? $items : iterator_to_array($items, false);

        $keys = [];
        foreach ($items as $item) {
            $keys[(string) data_get($item, $key)] = true;
        }

        // A parent that is not among the records (filtered out, deleted) makes its child a root.
        $byParent = [];
        foreach ($items as $item) {
            $parentKey = data_get($item, $parent);
            $byParent[$parentKey === null || ! isset($keys[(string) $parentKey]) ? '' : (string) $parentKey][] = $item;
        }

        $options = [];
        $walk = static function (string $parentKey, mixed $parentValue, bool $disabled) use (&$walk, &$options, $byParent, $label, $key, $excludeBranch): void {
            foreach ($byParent[$parentKey] ?? [] as $item) {
                $value = data_get($item, $key);
                $off = $disabled || ($excludeBranch !== null && (string) $value === (string) $excludeBranch);
                $options[] = array_filter([
                    'value' => $value,
                    'label' => (string) data_get($item, $label),
                    'parent' => $parentValue,
                    'disabled' => $off ?: null,
                ], static fn ($v) => $v !== null);
                $walk((string) $value, $value, $off);
            }
        };
        $walk('', null, false);

        return $options;
    }

    /**
     * The value and every value below it, e.g. a category with all its subcategories.
     *
     * @param list<array{value: int|string, parent?: int|string}> $options in tree order
     * @return list<int|string>
     */
    public static function branch(array $options, int|string $value): array
    {
        $inside = [(string) $value => true];
        $values = [];

        foreach ($options as $option) {
            $own = (string) $option['value'];

            if ($own === (string) $value || (isset($option['parent']) && isset($inside[(string) $option['parent']]))) {
                $inside[$own] = true;
                $values[] = $option['value'];
            }
        }

        return $values;
    }
}
