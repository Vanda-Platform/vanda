<?php

declare(strict_types=1);

namespace Vanda\Fields;

use DateTimeImmutable;
use DateTimeZone;

/**
 * A searchable Select of time zones with their current offset (Orchid's TimeZone):
 *
 *     TimeZone::make('form.timezone')->label('Time zone'),
 *     TimeZone::make('form.timezone', DateTimeZone::EUROPE | DateTimeZone::ASIA),
 */
final class TimeZone
{
    /**
     * @param int $regions DateTimeZone::ALL or a mix of DateTimeZone::EUROPE, ::ASIA, ...
     */
    public static function make(string $path, int $regions = DateTimeZone::ALL): Select
    {
        $now = new DateTimeImmutable;
        $options = array_map(static fn (string $zone) => [
            'value' => $zone,
            'label' => str_replace('_', ' ', $zone),
            'hint' => 'UTC'.$now->setTimezone(new DateTimeZone($zone))->format('P'),
        ], DateTimeZone::listIdentifiers($regions));

        return Select::make($path)->options($options)->searchable();
    }
}
