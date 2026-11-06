<?php

declare(strict_types=1);

namespace Orchid\Filters;

use Illuminate\Database\Eloquent\Builder;
use Orchid\Filters\Types\Type;

/**
 * Orchid's Filterable: a model lists what may be filtered and sorted from the URL,
 *
 *     protected $allowedFilters = ['title' => Like::class, 'created_at' => WhereDateStartEnd::class];
 *     protected $allowedSorts = ['id', 'title', 'created_at'];
 *
 * and Model::filters() applies ?filter[…] and ?sort=… to the query.
 * Columns not listed are ignored, as in Orchid.
 */
trait Filterable
{
    public function scopeFilters(Builder $query, mixed $kit = null): Builder
    {
        $filters = (array) request()->query('filter', []);

        foreach ($this->allowedFilters ?? [] as $column => $type) {
            if (is_int($column)) {
                [$column, $type] = [$type, Types\Where::class];
            }
            $value = $filters[$column] ?? null;
            if ($value === null || $value === '' || $value === []) {
                continue;
            }
            if (is_subclass_of($type, Type::class)) {
                $type::apply($query, $column, $value);
            }
        }

        $sort = (string) request()->query('sort', '');
        $column = ltrim($sort, '-');
        if ($column !== '' && in_array($column, $this->allowedSorts ?? [], true)) {
            $query->orderBy($column, str_starts_with($sort, '-') ? 'desc' : 'asc');
        }

        return $query;
    }

    /**
     * Sorts by this column unless the URL asks for another sort.
     */
    public function scopeDefaultSort(Builder $query, string $column, string $direction = 'asc'): Builder
    {
        $sort = ltrim((string) request()->query('sort', ''), '-');

        if ($sort === '' || ! in_array($sort, $this->allowedSorts ?? [], true)) {
            $query->orderBy($column, $direction);
        }

        return $query;
    }
}
