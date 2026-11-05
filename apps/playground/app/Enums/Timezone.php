<?php

namespace App\Enums;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Time zones offered in the user card, with their current UTC offset as a hint.
 */
final class Timezone
{
    public const ZONES = [
        'Europe/Kaliningrad', 'Europe/Moscow', 'Europe/Samara', 'Asia/Yekaterinburg', 'Asia/Novosibirsk',
        'Asia/Almaty', 'Europe/Berlin', 'Europe/Paris', 'Europe/London', 'UTC',
    ];

    /**
     * @return list<array{value: string, label: string, hint: string}>
     */
    public static function options(): array
    {
        return array_map(static fn (string $zone) => [
            'value' => $zone,
            'label' => $zone,
            'hint' => 'UTC'.(new DateTimeImmutable('now', new DateTimeZone($zone)))->format('P'),
        ], self::ZONES);
    }
}
