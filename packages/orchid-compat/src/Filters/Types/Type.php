<?php

declare(strict_types=1);

namespace Orchid\Filters\Types;

use Illuminate\Database\Eloquent\Builder;

/**
 * A filter type from a model's $allowedFilters, applied to ?filter[column]=value.
 */
abstract class Type
{
    abstract public static function apply(Builder $query, string $column, mixed $value): void;
}
