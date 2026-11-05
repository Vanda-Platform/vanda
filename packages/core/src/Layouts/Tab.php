<?php

declare(strict_types=1);

namespace Vanda\Layouts;

use Vanda\Schema\Node;
use Vanda\Schema\SchemaContext;

/**
 * One tab of Layout::tabs(); created by Tabs, not directly.
 */
final class Tab extends Node
{
    /**
     * @param list<Node> $nodes
     */
    public function __construct(
        private readonly string $title,
        private readonly array $nodes,
    ) {}

    public function type(): string
    {
        return 'tab';
    }

    public function children(): array
    {
        return $this->nodes;
    }

    protected function properties(SchemaContext $context): array
    {
        return ['title' => $this->title];
    }
}
