<?php

declare(strict_types=1);

use Vanda\Tests\TestCase;

uses(TestCase::class)->in(__DIR__);

/**
 * Signs in through the panel's login screen, as a browser does.
 */
function signInAs(string $email, string $password): array
{
    $page = test()->getJson('/panel/login', ['X-Vanda' => 'true'])->json();
    $button = collect($page['layout'])->firstWhere('type', 'button')['action'];
    test()->postJson('/panel/login', ['type' => 'action', 'action' => 'login', 'token' => $button['token'], 'snapshot' => $page['snapshot'], 'state' => ['form' => ['email' => $email, 'password' => $password]]])->assertOk();

    return test()->getJson('/admin/showcase', ['X-Vanda' => 'true'])->json();
}
