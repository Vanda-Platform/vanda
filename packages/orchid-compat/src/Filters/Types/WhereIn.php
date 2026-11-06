<?php

declare(strict_types=1);

namespace Orchid\Filters\Types;

use Illuminate\Database\Eloquent\Builder;

class WhereIn extends Type
{
    public static function apply(Builder $query, string $column, mixed $value): void
    {
        $query->whereIn($column, is_array($value) ? $value : explode(',', (string) $value));
    }
}
