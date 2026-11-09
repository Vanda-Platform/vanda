<?php

declare(strict_types=1);

namespace Vanda\Layouts;

use Closure;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Vanda\Fields\Concerns\HasOptions;
use Vanda\Schema\Get;
use Vanda\Support\Search;
use Vanda\Support\TreeOptions;

/**
 * A table filter. Its value travels in the URL as ?filter[name]=…, the same as in Orchid,
 * so filtered lists can be bookmarked and shared:
 *
 *     Filter::select('role', 'Role')->options(Role::pluck('name', 'slug'))
 *         ->apply(fn (Builder $query, string $slug) => $query->whereHas('roles', fn ($q) => $q->where('slug', $slug))),
 *     Filter::text('email', 'Email'),                       // email contains the text, any case
 *     Filter::dateRange('created', 'Created')->column('created_at'),
 *     Filter::select('category_id', 'Category')->tree(fn () => Category::all()),  // a category with its subcategories
 *
 * Without ->apply() the filter compares its column (the name by default) with the value.
 */
final class Filter
{
    use HasOptions;

    private ?string $column = null;

    private ?Closure $apply = null;

    /** @var array{items: iterable<mixed>|Closure, label: string, parent: string, key: string}|null */
    private ?array $tree = null;

    /** @var list<array{value: int|string, label: string, parent?: int|string, hint?: string}>|null built once per request */
    private ?array $choices = null;

    private function __construct(
        private readonly string $name,
        private readonly string $label,
        private readonly FilterType $kind,
    ) {}

    public static function select(string $name, string $label): self
    {
        return new self($name, $label, FilterType::Select);
    }

    public static function text(string $name, string $label): self
    {
        return new self($name, $label, FilterType::Text);
    }

    public static function dateRange(string $name, string $label): self
    {
        return new self($name, $label, FilterType::DateRange);
    }

    /**
     * Database column the default condition uses.
     */
    public function column(string $column): self
    {
        $this->column = $column;

        return $this;
    }

    /**
     * @param Closure(Builder $query, mixed $value): mixed $apply
     */
    public function apply(Closure $apply): self
    {
        $this->apply = $apply;

        return $this;
    }

    /**
     * Options from nested records, picked in a tree with search as in Select::tree():
     *
     *     Filter::select('category_id', 'Category')->tree(fn () => Category::query()->orderBy('sort')->get(), label: 'name')
     *
     * A record matches together with everything inside it: a parent category lists the products of all
     * its subcategories. With ->apply() the closure gets the chosen value and decides itself.
     *
     * @param iterable<mixed>|Closure(): iterable<mixed> $items records with key, label and parent key
     */
    public function tree(iterable|Closure $items, string $label = 'name', string $parent = 'parent_id', string $key = 'id'): self
    {
        $this->tree = ['items' => $items, 'label' => $label, 'parent' => $parent, 'key' => $key];
        $this->choices = null;

        return $this;
    }

    public function name(): string
    {
        return $this->name;
    }

    /**
     * The value from a filter array (usually request('filter')), cleaned for this kind;
     * null when the filter is not set.
     *
     * @param array<string, mixed> $filters
     * @return string|array{start: ?string, end: ?string}|null
     */
    public function value(array $filters): string|array|null
    {
        $raw = $filters[$this->name] ?? null;

        if ($this->kind === FilterType::DateRange) {
            $date = static fn (mixed $value): ?string => is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? $value : null;
            $range = ['start' => $date($raw['start'] ?? null), 'end' => $date($raw['end'] ?? null)];

            return is_array($raw) && ($range['start'] !== null || $range['end'] !== null) ? $range : null;
        }

        if (! is_scalar($raw) || trim((string) $raw) === '') {
            return null;
        }

        $value = trim((string) $raw);

        if ($this->kind === FilterType::Select) {
            $allowed = array_map(static fn (array $option) => (string) $option['value'], $this->choices());

            return in_array($value, $allowed, true) ? $value : null;
        }

        return $value;
    }

    /**
     * @param string|array{start: ?string, end: ?string} $value
     */
    public function applyTo(Builder $query, string|array $value): void
    {
        if ($this->apply !== null) {
            ($this->apply)($query, $value);

            return;
        }

        $column = $this->column ?? $this->name;

        match ($this->kind) {
            FilterType::Select => $this->tree !== null
                ? $query->whereIn($column, TreeOptions::branch($this->choices(), (string) $value))
                : $query->where($column, $value),
            FilterType::Text => Search::contains($query, $column, (string) $value),
            FilterType::DateRange => $query
                ->when($value['start'] ?? null, static fn ($q, $start) => $q->whereDate($column, '>=', $start))
                ->when($value['end'] ?? null, static fn ($q, $end) => $q->whereDate($column, '<=', $end)),
        };
    }

    /**
     * @param string|array{start: ?string, end: ?string}|null $value
     * @return array<string, mixed>
     */
    public function toArray(string|array|null $value): array
    {
        $options = $this->kind === FilterType::Select ? $this->choices() : null;

        return array_filter([
            'name' => $this->name,
            'label' => $this->label,
            'kind' => $this->kind->value,
            'options' => $options,
            'tree' => $this->tree !== null ? true : null,
            'value' => $value,
            'display' => $value === null ? null : $this->display($value, $options ?? []),
        ], static fn ($item) => $item !== null);
    }

    /**
     * @return list<array{value: int|string, label: string, parent?: int|string, hint?: string}>
     */
    private function choices(): array
    {
        if ($this->tree === null) {
            return $this->resolveOptions(new Get([]));
        }

        ['items' => $items, 'label' => $label, 'parent' => $parent, 'key' => $key] = $this->tree;

        return $this->choices ??= TreeOptions::build($items, $label, $parent, $key);
    }

    /**
     * @param string|array{start: ?string, end: ?string} $value
     * @param list<array{value: int|string, label: string}> $options
     */
    private function display(string|array $value, array $options): string
    {
        if (is_array($value)) {
            $format = static fn (?string $date) => $date === null ? '…' : date('d.m.Y', (int) strtotime($date));

            return $format($value['start']).' – '.$format($value['end']);
        }

        foreach ($options as $option) {
            if ((string) $option['value'] === $value) {
                return $option['label'];
            }
        }

        return $value;
    }
}
