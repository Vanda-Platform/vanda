<?php

declare(strict_types=1);

namespace Orchid\Screen;

use ArrayAccess;
use Countable;
use Illuminate\Support\Arr;

/**
 * Orchid's Repository: an array with dot-notation access, used by services that prepare
 * data for screens.
 *
 * @implements ArrayAccess<string, mixed>
 */
class Repository implements ArrayAccess, Countable
{
    /**
     * @param array<string, mixed> $items
     */
    public function __construct(protected array $items = []) {}

    public function get(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->items, $key, $default);
    }

    public function getContent(string $key, mixed $default = null): mixed
    {
        return $this->get($key, $default);
    }

    public function has(string $key): bool
    {
        return Arr::has($this->items, $key);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->items;
    }

    public function offsetExists(mixed $offset): bool
    {
        return $this->has((string) $offset);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->get((string) $offset);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        Arr::set($this->items, (string) $offset, $value);
    }

    public function offsetUnset(mixed $offset): void
    {
        Arr::forget($this->items, (string) $offset);
    }

    public function count(): int
    {
        return count($this->items);
    }
}
