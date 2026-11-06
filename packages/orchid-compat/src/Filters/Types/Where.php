<?php

declare(strict_types=1);

namespace Orchid\Filters\Types;

use Illuminate\Database\Eloquent\Builder;

class Where extends Type
{
    public static function apply(Builder $query, string $column, mixed $value): void
    {
        is_array($value) ? $query->whereIn($column, $value) : $query->where($column, $value);
    }
}
