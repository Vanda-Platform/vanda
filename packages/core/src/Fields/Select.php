<?php

declare(strict_types=1);

namespace Vanda\Fields;

use Closure;
use Vanda\Fields\Concerns\HasOptions;
use Vanda\Schema\Get;
use Vanda\Schema\SchemaContext;

/**
 * Pick one value, or several with ->multiple():
 *
 *     Select::make('form.city')
 *         ->dependsOn('form.country')
 *         ->options(fn (Get $get) => City::where('country', $get('form.country'))->pluck('name', 'id'))
 */
final class Select extends Field
{
    use HasOptions;

    private bool $multiple = false;

    private bool $searchable = false;

    private bool $allowCustom = false;

    /** @var array{items: iterable<mixed>|Closure, label: string, parent: string, key: string}|null */
    private ?array $tree = null;

    private int|string|null $excludeBranch = null;

    public function type(): string
    {
        return $this->tree === null ? 'select' : 'treeselect';
    }

    /**
     * Options from nested records, shown as an expandable tree with search:
     *
     *     Select::make('page.parent_id')->tree(fn () => Page::all(), label: 'title')->excludeBranch($page->id)
     *
     * With ->multiple() checking a branch checks everything inside it.
     *
     * @param iterable<mixed>|Closure(): iterable<mixed> $items records with key, label and parent key
     */
    public function tree(iterable|Closure $items, string $label = 'name', string $parent = 'parent_id', string $key = 'id'): static
    {
        $this->tree = ['items' => $items, 'label' => $label, 'parent' => $parent, 'key' => $key];
        $this->searchable = true;

        return $this;
    }

    /**
     * This record and everything inside it cannot be chosen, e.g. a page cannot be
     * moved into itself or its own children.
     */
    public function excludeBranch(int|string|null $key): static
    {
        $this->excludeBranch = $key;

        return $this;
    }

    /**
     * A tree select keeps only values that are among its enabled options.
     */
    public function sanitize(mixed $value, mixed $current = null): mixed
    {
        if ($this->tree === null) {
            return $value;
        }

        $allowed = [];
        foreach ($this->treeOptions() as $option) {
            if (! ($option['disabled'] ?? false)) {
                $allowed[(string) $option['value']] = $option['value'];
            }
        }

        if ($this->multiple) {
            return array_values(array_map(
                static fn ($item) => $allowed[(string) $item],
                array_filter(is_array($value) ? $value : [], static fn ($item) => is_scalar($item) && isset($allowed[(string) $item])),
            ));
        }

        return is_scalar($value) && isset($allowed[(string) $value]) ? $allowed[(string) $value] : null;
    }

    /** The value becomes a list; chosen items are shown as removable tags. */
    public function multiple(bool $multiple = true): static
    {
        $this->multiple = $multiple;

        return $this;
    }

    /** Adds a search box to the list of options. */
    public function searchable(bool $searchable = true): static
    {
        $this->searchable = $searchable;

        return $this;
    }

    /**
     * The user may type a value that is not among the options (tags, Orchid's taggable()).
     * Without it, only the options are accepted.
     */
    public function allowCustom(bool $allowed = true): static
    {
        $this->allowCustom = $allowed;
        $this->searchable = $this->searchable || $allowed;

        return $this;
    }

    /**
     * Only the options pass, checked against the options at the moment of the action.
     */
    public function implicitRules(Get $get): array
    {
        if ($this->tree !== null || $this->allowCustom) {
            return [];
        }

        $allowed = array_map(static fn (array $option) => (string) $option['value'], $this->resolveOptions($get));

        return [static function (string $attribute, mixed $value, Closure $fail) use ($allowed): void {
            foreach (is_array($value) ? $value : [$value] as $item) {
                if ($item !== null && $item !== '' && (! is_scalar($item) || ! in_array((string) $item, $allowed, true))) {
                    $fail('validation.in')->translate();

                    return;
                }
            }
        }];
    }

    protected function fieldProperties(SchemaContext $context): array
    {
        return [
            'options' => $this->tree === null ? $this->resolveOptions($context->get()) : $this->treeOptions(),
            'multiple' => $this->multiple,
            'searchable' => $this->searchable,
            'allowCustom' => $this->allowCustom,
        ];
    }

    /**
     * Records in tree order (parents before children), with parent keys and disabled branches.
     *
     * @return list<array{value: int|string, label: string, parent?: int|string, disabled?: true}>
     */
    private function treeOptions(): array
    {
        ['items' => $items, 'label' => $label, 'parent' => $parent, 'key' => $key] = (array) $this->tree;
        $items = $items instanceof Closure ? $items() : $items;
        $items = is_array($items) ? $items : iterator_to_array($items, false);

        $keys = [];
        foreach ($items as $item) {
            $keys[(string) data_get($item, $key)] = true;
        }

        $byParent = [];
        foreach ($items as $item) {
            $parentKey = data_get($item, $parent);
            $byParent[$parentKey === null || ! isset($keys[(string) $parentKey]) ? '' : (string) $parentKey][] = $item;
        }

        $options = [];
        $walk = function (string $parentKey, mixed $parentValue, bool $disabled) use (&$walk, &$options, $byParent, $label, $key): void {
            foreach ($byParent[$parentKey] ?? [] as $item) {
                $value = data_get($item, $key);
                $off = $disabled || ($this->excludeBranch !== null && (string) $value === (string) $this->excludeBranch);
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
}
