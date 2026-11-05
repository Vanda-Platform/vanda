<?php

declare(strict_types=1);

namespace Vanda\Access;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Add to the user model. Personal permissions (`users.permissions`) override
 * what roles grant: true allows, false denies, absent inherits from roles.
 *
 * @property array<string, bool|int|string>|null $permissions
 */
trait HasPermissions
{
    public function initializeHasPermissions(): void
    {
        $this->mergeCasts(['permissions' => 'array']);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_users', 'user_id', 'role_id');
    }

    public function hasAccess(string $permission): bool
    {
        $own = Permissions::isOn(($this->permissions ?? [])[$permission] ?? null);

        if ($own !== null) {
            return $own;
        }

        return $this->roles->contains(static fn (Role $role) => $role->grants($permission));
    }

    /**
     * @param iterable<string> $permissions
     */
    public function hasAnyAccess(iterable $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->hasAccess($permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Names of this user's roles that grant each permission, for the UI.
     *
     * @return array<string, list<string>>
     */
    public function permissionSources(): array
    {
        $sources = [];

        foreach ($this->roles as $role) {
            foreach (array_keys($role->permissions ?? []) as $permission) {
                if ($role->grants($permission)) {
                    $sources[$permission][] = $role->name;
                }
            }
        }

        return $sources;
    }

    /**
     * @param iterable<int|string> $roleIds
     */
    public function replaceRoles(iterable $roleIds): void
    {
        $this->roles()->sync(is_array($roleIds) ? $roleIds : iterator_to_array($roleIds, false));
        $this->unsetRelation('roles');
    }
}
