<?php

declare(strict_types=1);

namespace Orchid\Platform\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Orchid\Filters\Filterable;
use Orchid\Filters\Types\Like;
use Orchid\Filters\Types\Where;
use Orchid\Filters\Types\WhereDateStartEnd;
use Orchid\Screen\AsSource;
use Vanda\Access\HasPermissions;

/**
 * Orchid's platform User on Vanda's permissions: `users.permissions`, roles in `role_users`.
 */
class User extends Authenticatable
{
    use AsSource, Filterable, HasFactory, HasPermissions, Notifiable;

    protected $fillable = ['name', 'email', 'password', 'permissions'];

    protected $hidden = ['password', 'remember_token', 'permissions'];

    protected $casts = [
        'permissions' => 'array',
        'email_verified_at' => 'datetime',
    ];

    protected $allowedFilters = [
        'id' => Where::class,
        'name' => Like::class,
        'email' => Like::class,
        'updated_at' => WhereDateStartEnd::class,
        'created_at' => WhereDateStartEnd::class,
    ];

    protected $allowedSorts = ['id', 'name', 'email', 'updated_at', 'created_at'];
}
