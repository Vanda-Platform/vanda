<?php

declare(strict_types=1);

namespace Vanda\Fields;

use Vanda\Schema\SchemaContext;

/**
 * Date (and optionally time) with a calendar. The value is an ISO string:
 * "2026-03-12", with ->withTime() "2026-03-12T14:30", with ->timeOnly() "14:30".
 */
final class DateTimer extends Field
{
    private bool $withTime = false;

    private bool $timeOnly = false;

    private ?string $min = null;

    private ?string $max = null;

    public function type(): string
    {
        return 'date';
    }

    public function withTime(bool $withTime = true): static
    {
        $this->withTime = $withTime;

        return $this;
    }

    /**
     * Only the time of day, "HH:MM" (Orchid's noCalendar()).
     */
    public function timeOnly(bool $timeOnly = true): static
    {
        $this->timeOnly = $timeOnly;

        return $this;
    }

    /** Earliest allowed date, "YYYY-MM-DD". */
    public function min(string $date): static
    {
        $this->min = $date;

        return $this;
    }

    /** Latest allowed date, "YYYY-MM-DD". */
    public function max(string $date): static
    {
        $this->max = $date;

        return $this;
    }

    protected function fieldProperties(SchemaContext $context): array
    {
        return ['withTime' => $this->withTime, 'timeOnly' => $this->timeOnly, 'min' => $this->min, 'max' => $this->max];
    }
}
