<?php

declare(strict_types=1);

namespace Orchid\Filters\Types;

use Illuminate\Database\Eloquent\Builder;

/**
 * ?filter[created_at][start]=2026-01-01&filter[created_at][end]=2026-01-31
 */
class WhereDateStartEnd extends Type
{
    public static function apply(Builder $query, string $column, mixed $value): void
    {
        $value = is_array($value) ? $value : [];
        $query->when($value['start'] ?? null, static fn ($q, $start) => $q->whereDate($column, '>=', $start))
            ->when($value['end'] ?? null, static fn ($q, $end) => $q->whereDate($column, '<=', $end));
    }
}
