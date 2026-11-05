<?php

declare(strict_types=1);

namespace Vanda\Access;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A role grants a set of permissions. Same table layout as Orchid
 * (`roles`, `role_users`), so an Orchid database works as is.
 *
 * @property string $slug
 * @property string $name
 * @property array<string, bool|int|string>|null $permissions
 */
class Role extends Model
{
    protected $table = 'roles';

    protected $fillable = ['slug', 'name', 'permissions'];

    protected function casts(): array
    {
        return ['permissions' => 'array'];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany((string) config('auth.providers.users.model'), 'role_users', 'role_id', 'user_id');
    }

    public function grants(string $permission): bool
    {
        return Permissions::isOn(($this->permissions ?? [])[$permission] ?? null) === true;
    }
}
