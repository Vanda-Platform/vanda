<?php

declare(strict_types=1);

use Symfony\Component\HttpKernel\Exception\HttpException;
use Vanda\State\Snapshot;

it('round-trips signed state as JSON', function () {
    $token = Snapshot::seal('App\\Screen', '/admin/users', ['form' => ['name' => 'Анна']]);

    expect(Snapshot::open($token, 'App\\Screen', '/admin/users'))->toBe(['form' => ['name' => 'Анна']])
        ->and($token)->not->toContain('O:'); // never PHP serialize
});

it('rejects a tampered snapshot', function () {
    $token = Snapshot::seal('App\\Screen', '/a', ['admin' => false]);
    [$payload, $signature] = explode('.', $token);
    $forged = rtrim(strtr(base64_encode(json_encode(['s' => 'App\\Screen', 'u' => '/a', 'd' => ['admin' => true]])), '+/', '-_'), '=');

    Snapshot::open($forged.'.'.$signature, 'App\\Screen', '/a');
})->throws(HttpException::class, 'invalid or out of date');

it('rejects a snapshot from another screen or url', function () {
    $token = Snapshot::seal('App\\Screen', '/a', []);

    Snapshot::open($token, 'App\\Screen', '/b');
})->throws(HttpException::class, 'another screen');
