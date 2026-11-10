<?php

declare(strict_types=1);

namespace Vanda\Settings\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Vanda\Access\HasPermissions;

final class User extends Authenticatable
{
    use HasPermissions;

    protected $table = 'users';

    protected $guarded = [];
}
