<?php

declare(strict_types=1);

namespace Orchid\Filters\Types;

use Illuminate\Database\Eloquent\Builder;

class WhereDate extends Type
{
    public static function apply(Builder $query, string $column, mixed $value): void
    {
        $query->whereDate($column, $value);
    }
}
