<?php

declare(strict_types=1);

namespace Vanda\Layouts;

/**
 * One KPI card of Layout::metrics():
 *
 *     Metric::make('Revenue', '1 684 200 ₽')->delta('+12.4%', good: true)->trend([12, 14, 13, 18, 21])
 */
final class Metric
{
    private ?string $delta = null;

    private ?bool $good = null;

    /** @var list<float> */
    private array $trend = [];

    private ?string $hint = null;

    private function __construct(
        private readonly string $label,
        private readonly string $value,
    ) {}

    public static function make(string $label, string|int|float $value): self
    {
        return new self($label, (string) $value);
    }

    public function delta(string $delta, bool $good = true): self
    {
        $this->delta = $delta;
        $this->good = $good;

        return $this;
    }

    /**
     * @param list<int|float> $values a small series for the sparkline
     */
    public function trend(array $values): self
    {
        $this->trend = array_map('floatval', array_values($values));

        return $this;
    }

    public function hint(string $hint): self
    {
        $this->hint = $hint;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'label' => $this->label,
            'value' => $this->value,
            'delta' => $this->delta,
            'good' => $this->good,
            'trend' => $this->trend,
            'hint' => $this->hint,
        ], static fn ($value) => $value !== null && $value !== []);
    }
}
