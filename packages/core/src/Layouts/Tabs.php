<?php

declare(strict_types=1);

namespace Vanda\Layouts;

use Vanda\Schema\Node;
use Vanda\Schema\SchemaContext;

/**
 * Tabs switch on the client; every tab is part of the form, so fields in
 * hidden tabs are still validated and a tab with errors is marked.
 */
final class Tabs extends Node
{
    /**
     * @param list<Tab> $tabs
     */
    private function __construct(private readonly array $tabs) {}

    /**
     * @param array<string, list<Node>> $tabs title => nodes
     */
    public static function make(array $tabs): self
    {
        return (new self(array_map(
            static fn (string $title, array $nodes) => new Tab($title, array_values($nodes)),
            array_keys($tabs),
            $tabs,
        )))->captureSource();
    }

    public function type(): string
    {
        return 'tabs';
    }

    public function children(): array
    {
        return $this->tabs;
    }

    protected function properties(SchemaContext $context): array
    {
        return [];
    }
}
