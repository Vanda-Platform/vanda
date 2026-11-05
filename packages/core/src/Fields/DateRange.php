<?php

declare(strict_types=1);

namespace Vanda\Fields;

/**
 * A period of two dates. The value is ['start' => "YYYY-MM-DD", 'end' => "YYYY-MM-DD"].
 */
final class DateRange extends Field
{
    public function type(): string
    {
        return 'daterange';
    }
}
