<?php

declare(strict_types=1);

use Vanda\Settings\Tests\TestCase;
use Vanda\Settings\Tests\Fixtures\User;

uses(TestCase::class)->in(__DIR__);

/** A user of the panel with these abilities, signed in. */
function signedIn(array $abilities = []): User
{
    $user = User::query()->create([
        'name' => 'Vera Mironova',
        'email' => 'vera'.User::query()->count().'@example.com',
        'permissions' => array_fill_keys($abilities, true),
    ]);
    test()->actingAs($user);

    return $user;
}
