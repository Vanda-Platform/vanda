<?php

declare(strict_types=1);

namespace Vanda\Layouts;

use Vanda\Schema\Node;
use Vanda\Schema\SchemaContext;

final class Metrics extends Node
{
    /**
     * @param list<Metric> $metrics
     */
    public function __construct(private readonly array $metrics) {}

    /**
     * @param list<Metric> $metrics
     */
    public static function make(array $metrics): self
    {
        return (new self(array_values($metrics)))->captureSource();
    }

    public function type(): string
    {
        return 'metrics';
    }

    protected function properties(SchemaContext $context): array
    {
        return ['items' => array_map(static fn (Metric $metric) => $metric->toArray(), $this->metrics)];
    }
}
