<?php

declare(strict_types=1);

namespace Vanda\Schema;

/**
 * Read-only access to the current screen state inside option closures:
 *
 *     ->options(fn (Get $get) => City::where('country', $get('form.country'))->pluck('name', 'id'))
 */
final class Get
{
    /**
     * @param array<string, mixed> $state
     */
    public function __construct(private readonly array $state) {}

    public function __invoke(string $path, mixed $default = null): mixed
    {
        return data_get($this->state, $path, $default);
    }
}
