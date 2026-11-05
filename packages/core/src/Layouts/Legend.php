<?php

declare(strict_types=1);

namespace Vanda\Layouts;

use Vanda\Schema\Node;
use Vanda\Schema\SchemaContext;

/**
 * Read-only card of one record (Orchid's Legend):
 *
 *     Layout::legend($order, [Sight::make('id', 'Order')->copyable()->mono(), Sight::make('total', 'Total')])
 */
final class Legend extends Node
{
    /**
     * @param list<Sight> $sights
     */
    public function __construct(
        private readonly mixed $record,
        private readonly array $sights,
    ) {}

    /**
     * @param list<Sight> $sights
     */
    public static function make(mixed $record, array $sights): self
    {
        return (new self($record, array_values($sights)))->captureSource();
    }

    public function type(): string
    {
        return 'legend';
    }

    protected function properties(SchemaContext $context): array
    {
        return [
            'items' => array_map(
                fn (Sight $sight, int $index) => $sight->toArray($this->record, $context, $this->id.'.'.$index),
                $this->sights,
                array_keys($this->sights),
            ),
        ];
    }
}
