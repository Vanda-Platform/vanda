<?php

declare(strict_types=1);

namespace Vanda\Navigation;

use Illuminate\Support\Facades\Gate;
use Vanda\Access\Authorizer;
use Vanda\Screen\ScreenRegistry;

/**
 * An entry of the sidebar:
 *
 *     MenuItem::make('Users')->icon('users')->to(UserListScreen::class)->group('Access')
 *
 * An item that leads to a screen the user may not open (#[Can]) is hidden.
 */
final class MenuItem
{
    private ?string $href = null;

    private ?string $screen = null;

    private ?string $ability = null;

    private ?string $icon = null;

    private ?string $group = null;

    private int|string|null $badge = null;

    private function __construct(private readonly string $label) {}

    public static function make(string $label): self
    {
        return new self($label);
    }

    /**
     * @param class-string $screen
     * @param array<string, mixed> $parameters
     */
    public function to(string $screen, array $parameters = []): self
    {
        $this->screen = $screen;
        $this->href = app(ScreenRegistry::class)->url($screen, $parameters);

        return $this;
    }

    public function href(string $url): self
    {
        $this->href = $url;

        return $this;
    }

    /** Show only to users who have this Gate ability or Vanda permission. */
    public function can(string $ability): self
    {
        $this->ability = $ability;

        return $this;
    }

    public function icon(string $icon): self
    {
        $this->icon = $icon;

        return $this;
    }

    /** Items with the same group are shown together under its title. */
    public function group(string $group): self
    {
        $this->group = $group;

        return $this;
    }

    public function badge(int|string $badge): self
    {
        $this->badge = $badge;

        return $this;
    }

    public function isAllowed(): bool
    {
        return ($this->ability === null || Gate::allows($this->ability))
            && ($this->screen === null || Authorizer::allowsScreenClass($this->screen));
    }

    public function getHref(): ?string
    {
        return $this->href;
    }

    /**
     * Length of the item's path when it is the current page or one of its parents, else 0.
     * The item with the longest match is the active one ("/admin" vs "/admin/users").
     */
    public function matchLength(string $currentPath): int
    {
        if ($this->href === null) {
            return 0;
        }

        $path = '/'.trim((string) parse_url($this->href, PHP_URL_PATH), '/');

        return $currentPath === $path || str_starts_with($currentPath, rtrim($path, '/').'/') ? strlen($path) : 0;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(bool $active = false): array
    {
        return array_filter([
            'label' => $this->label,
            'href' => $this->href,
            'icon' => $this->icon,
            'group' => $this->group,
            'badge' => $this->badge,
            'active' => $active,
        ], static fn ($value) => $value !== null && $value !== false);
    }
}
