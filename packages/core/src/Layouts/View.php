<?php

declare(strict_types=1);

namespace Vanda\Layouts;

/**
 * A named set of filter values shown as a tab above a table, with a row count:
 *
 *     ->views([
 *         View::make('All'),
 *         View::make('Admins', ['role' => 'admin']),
 *         View::make('Blocked', ['status' => 'blocked']),
 *     ])
 */
final class View
{
    /**
     * @param array<string, mixed> $filters filter name => value, as in ?filter[…]
     */
    private function __construct(
        private readonly string $label,
        private readonly array $filters,
    ) {}

    /**
     * @param array<string, mixed> $filters
     */
    public static function make(string $label, array $filters = []): self
    {
        return new self($label, $filters);
    }

    public function label(): string
    {
        return $this->label;
    }

    /**
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        return $this->filters;
    }
}
