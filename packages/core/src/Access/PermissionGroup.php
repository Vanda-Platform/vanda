<?php

declare(strict_types=1);

namespace Vanda\Access;

/**
 * A titled set of permissions shown together in the UI:
 *
 *     PermissionGroup::make('System')->add('platform.users', 'Users')->add('platform.roles', 'Roles')
 */
final class PermissionGroup
{
    /** @var array<string, string> key => label */
    private array $items = [];

    private function __construct(private readonly string $title) {}

    public static function make(string $title): self
    {
        return new self($title);
    }

    public function add(string $key, string $label): self
    {
        $this->items[$key] = $label;

        return $this;
    }

    public function title(): string
    {
        return $this->title;
    }

    /**
     * @return array<string, string>
     */
    public function items(): array
    {
        return $this->items;
    }
}
