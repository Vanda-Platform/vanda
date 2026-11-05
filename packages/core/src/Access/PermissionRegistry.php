<?php

declare(strict_types=1);

namespace Vanda\Access;

use Closure;

/**
 * Every permission the panel knows about. Registered keys become Gate abilities,
 * so `$user->can('platform.users')` and `@can` work as usual.
 */
final class PermissionRegistry
{
    /** @var list<Closure(): iterable<PermissionGroup>> */
    private array $definitions = [];

    /** @var list<PermissionGroup>|null */
    private ?array $groups = null;

    /**
     * @param Closure(): iterable<PermissionGroup> $groups
     */
    public function define(Closure $groups): void
    {
        $this->definitions[] = $groups;
        $this->groups = null;
    }

    /**
     * @return list<PermissionGroup>
     */
    public function groups(): array
    {
        if ($this->groups !== null) {
            return $this->groups;
        }

        $groups = [];
        foreach ($this->definitions as $definition) {
            foreach ($definition() as $group) {
                $groups[] = $group;
            }
        }

        return $this->groups = $groups;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->labels());
    }

    /**
     * @return array<string, string> key => label
     */
    public function labels(): array
    {
        return array_merge([], ...array_map(static fn (PermissionGroup $group) => $group->items(), $this->groups()));
    }
}
