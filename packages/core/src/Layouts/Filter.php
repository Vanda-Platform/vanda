<?php

declare(strict_types=1);

namespace Vanda\Layouts;

use Closure;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Vanda\Fields\Concerns\HasOptions;
use Vanda\Schema\Get;
use Vanda\Support\Search;

/**
 * A table filter. Its value travels in the URL as ?filter[name]=…, the same as in Orchid,
 * so filtered lists can be bookmarked and shared:
 *
 *     Filter::select('role', 'Role')->options(Role::pluck('name', 'slug'))
 *         ->apply(fn (Builder $query, string $slug) => $query->whereHas('roles', fn ($q) => $q->where('slug', $slug))),
 *     Filter::text('email', 'Email'),                       // email contains the text, any case
 *     Filter::dateRange('created', 'Created')->column('created_at'),
 *
 * Without ->apply() the filter compares its column (the name by default) with the value.
 */
final class Filter
{
    use HasOptions;

    private ?string $column = null;

    private ?Closure $apply = null;

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
            $allowed = array_map(static fn (array $option) => (string) $option['value'], $this->resolveOptions(new Get([])));

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
            FilterType::Select => $query->where($column, $value),
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
        $options = $this->kind === FilterType::Select ? $this->resolveOptions(new Get([])) : null;

        return array_filter([
            'name' => $this->name,
            'label' => $this->label,
            'kind' => $this->kind->value,
            'options' => $options,
            'value' => $value,
            'display' => $value === null ? null : $this->display($value, $options ?? []),
        ], static fn ($item) => $item !== null);
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
