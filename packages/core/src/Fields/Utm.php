<?php

declare(strict_types=1);

namespace Vanda\Fields;

/**
 * A link with UTM tags (Orchid's UTM): the address and a panel to fill in
 * utm_source, utm_medium, utm_campaign, utm_term and utm_content, which are written into it.
 *
 *     Utm::make('form.link')->label('Link in the newsletter'),
 *
 * The value is the whole URL as a string.
 */
final class Utm extends Field
{
    public function type(): string
    {
        return 'utm';
    }

    public function sanitize(mixed $value, mixed $current = null): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
