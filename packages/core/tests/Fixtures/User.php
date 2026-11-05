<?php

declare(strict_types=1);

namespace Vanda\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

final class User extends Model
{
    protected $table = 'users';

    protected $guarded = [];
}
