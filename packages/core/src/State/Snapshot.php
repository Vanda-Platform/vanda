<?php

declare(strict_types=1);

namespace Vanda\State;

use Symfony\Component\HttpKernel\Exception\HttpException;
use Vanda\Support\Signer;

/**
 * Server state of a screen between requests: signed JSON, never PHP serialize.
 *
 * The browser stores the token and sends it back; any change to it, or using it
 * on another screen or URL, is rejected.
 */
final class Snapshot
{
    /**
     * @param array<string, mixed> $state
     */
    public static function seal(string $screen, string $scope, array $state): string
    {
        $payload = self::encode(json_encode(
            ['s' => $screen, 'u' => $scope, 'd' => $state],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION,
        ));

        return $payload.'.'.Signer::sign($payload);
    }

    /**
     * @return array<string, mixed>
     */
    public static function open(string $token, string $screen, string $scope): array
    {
        [$payload, $signature] = array_pad(explode('.', $token, 2), 2, '');

        if ($payload === '' || ! Signer::verify($payload, $signature)) {
            throw new HttpException(409, 'The page state is invalid or out of date. Reload the page.');
        }

        $data = json_decode(self::decode($payload), true, flags: JSON_THROW_ON_ERROR);

        if (($data['s'] ?? null) !== $screen || ($data['u'] ?? null) !== $scope) {
            throw new HttpException(409, 'The page state belongs to another screen. Reload the page.');
        }

        return (array) ($data['d'] ?? []);
    }

    private static function encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function decode(string $value): string
    {
        return (string) base64_decode(strtr($value, '-_', '+/'), true);
    }
}
