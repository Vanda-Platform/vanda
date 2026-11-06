<?php

declare(strict_types=1);

namespace Vanda\Access;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Vanda\Activity\ActivityOptions;
use Vanda\Activity\RecordsActivity;

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
    use RecordsActivity;

    protected $table = 'roles';

    protected $fillable = ['slug', 'name', 'permissions'];

    protected function casts(): array
    {
        return ['permissions' => 'array'];
    }

    /**
     * Changes of roles go to the activity log: who gave which rights.
     */
    public function activityOptions(): ActivityOptions
    {
        return ActivityOptions::make((string) __('vanda::activity.role.type'))
            ->labels([
                'name' => (string) __('vanda::activity.role.name'),
                'slug' => (string) __('vanda::activity.role.slug'),
                'permissions' => (string) __('vanda::activity.role.permissions'),
            ]);
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
