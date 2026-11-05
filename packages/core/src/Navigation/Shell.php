<?php

declare(strict_types=1);

namespace Vanda\Navigation;

use Closure;

/**
 * Data of the panel around a screen: name, brand colour, menu and current user.
 */
final class Shell
{
    private ?Closure $menu = null;

    /**
     * @param Closure(): iterable<MenuItem> $items
     */
    public function menu(Closure $items): void
    {
        $this->menu = $items;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(string $currentPath): array
    {
        $items = $this->menu ? ($this->menu)() : [];
        $user = auth()->user();

        return [
            'name' => config('vanda.name', 'Vanda'),
            'brand' => config('vanda.brand', '#12805C'),
            'menu' => array_values(array_map(
                static fn (MenuItem $item) => $item->toArray($currentPath),
                is_array($items) ? $items : iterator_to_array($items, false),
            )),
            'user' => $user === null ? null : [
                'name' => (string) ($user->name ?? $user->email ?? ''),
                'email' => (string) ($user->email ?? ''),
            ],
        ];
    }
}
