<?php

declare(strict_types=1);

namespace Vanda\Navigation;

use Vanda\Screen\ScreenRegistry;

/**
 * An entry of the sidebar:
 *
 *     MenuItem::make('Users')->icon('users')->to(UserListScreen::class)->group('Access')
 */
final class MenuItem
{
    private ?string $href = null;

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
        $this->href = app(ScreenRegistry::class)->url($screen, $parameters);

        return $this;
    }

    public function href(string $url): self
    {
        $this->href = $url;

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

    /**
     * @return array<string, mixed>
     */
    public function toArray(string $currentPath): array
    {
        $path = $this->href === null ? null : '/'.ltrim((string) parse_url($this->href, PHP_URL_PATH), '/');
        $active = $path !== null && ($currentPath === $path || str_starts_with($currentPath, rtrim($path, '/').'/'));

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
