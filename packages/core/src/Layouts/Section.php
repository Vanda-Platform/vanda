<?php

declare(strict_types=1);

namespace Vanda\Layouts;

use Vanda\Schema\Node;
use Vanda\Schema\SchemaContext;

/**
 * A titled block: description on the left, content in a card on the right.
 */
final class Section extends Node
{
    /** @var list<Node> */
    private array $schema = [];

    /** @var list<Node> */
    private array $commands = [];

    public function __construct(
        private readonly string $title,
        private readonly ?string $description = null,
    ) {}

    public static function make(string $title, ?string $description = null): self
    {
        return (new self($title, $description))->captureSource();
    }

    public function type(): string
    {
        return 'section';
    }

    /**
     * @param list<Node> $nodes
     */
    public function schema(array $nodes): self
    {
        $this->schema = array_values($nodes);

        return $this;
    }

    /**
     * Buttons shown under the card, e.g. a "Save" that only saves this section.
     *
     * @param list<Node> $buttons
     */
    public function commands(array $buttons): self
    {
        $this->commands = array_values($buttons);

        return $this;
    }

    public function children(): array
    {
        return [...$this->schema, ...$this->commands];
    }

    protected function properties(SchemaContext $context): array
    {
        return [
            'title' => $this->title,
            'description' => $this->description,
            'commandIds' => array_map(static fn (Node $node) => $node->getId(), $this->commands),
        ];
    }
}
