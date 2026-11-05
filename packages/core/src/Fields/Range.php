<?php

declare(strict_types=1);

namespace Vanda\Fields;

use Vanda\Schema\SchemaContext;

/**
 * A slider for one number.
 */
final class Range extends Field
{
    private float $min = 0;

    private float $max = 100;

    private float $step = 1;

    private ?string $suffix = null;

    public function type(): string
    {
        return 'range';
    }

    public function between(float $min, float $max, float $step = 1): static
    {
        $this->min = $min;
        $this->max = $max;
        $this->step = $step;

        return $this;
    }

    /** Unit shown after the value, e.g. "%" or "₽". */
    public function suffix(string $suffix): static
    {
        $this->suffix = $suffix;

        return $this;
    }

    protected function fieldProperties(SchemaContext $context): array
    {
        return ['min' => $this->min, 'max' => $this->max, 'step' => $this->step, 'suffix' => $this->suffix];
    }
}
