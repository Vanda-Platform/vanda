<?php

declare(strict_types=1);

namespace Vanda\Layouts;

use Vanda\Schema\Node;
use Vanda\Schema\SchemaContext;

/**
 * Lays children out in equal columns; collapses to one column on phones.
 */
final class Grid extends Node
{
    /**
     * @param list<Node> $nodes
     */
    public function __construct(
        private readonly array $nodes,
        private readonly int $columns = 1,
    ) {}

    /**
     * @param list<Node> $nodes
     */
    public static function make(array $nodes, int $columns = 1): self
    {
        return (new self(array_values($nodes), $columns))->captureSource();
    }

    public function type(): string
    {
        return 'grid';
    }

    public function children(): array
    {
        return $this->nodes;
    }

    protected function properties(SchemaContext $context): array
    {
        return ['columns' => $this->columns];
    }
}
