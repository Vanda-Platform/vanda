<?php

declare(strict_types=1);

namespace Vanda\Navigation;

use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

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
     * Menu items the current user may open.
     *
     * @return list<MenuItem>
     */
    public function items(): array
    {
        $items = $this->menu ? ($this->menu)() : [];

        return array_values(array_filter(
            is_array($items) ? $items : iterator_to_array($items, false),
            static fn (MenuItem $item) => $item->isAllowed(),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(string $currentPath): array
    {
        $user = Auth::guard(config('vanda.guard'))->user();
        $items = $user ? $this->items() : [];
        $lengths = array_map(static fn (MenuItem $item) => $item->matchLength($currentPath), $items);
        $active = $lengths !== [] && max($lengths) > 0 ? array_search(max($lengths), $lengths, true) : null;

        return [
            'name' => config('vanda.name', 'Vanda'),
            'brand' => config('vanda.brand', '#12805C'),
            'menu' => array_map(static fn (MenuItem $item, int $index) => $item->toArray($index === $active), $items, array_keys($items)),
            'user' => $user === null ? null : array_filter([
                'name' => (string) ($user->name ?? $user->email ?? ''),
                'email' => (string) ($user->email ?? ''),
                'logout' => Route::has('vanda.logout') ? route('vanda.logout') : null,
            ]),
        ];
    }
}
