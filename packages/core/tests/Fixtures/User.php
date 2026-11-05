<?php

declare(strict_types=1);

namespace Vanda\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Vanda\Access\HasPermissions;

final class User extends Authenticatable
{
    use HasPermissions, Notifiable;

    protected $table = 'users';

    protected $guarded = [];

    protected $hidden = ['password'];
}
