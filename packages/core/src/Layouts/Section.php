<?php

declare(strict_types=1);

namespace Vanda\Layouts;

use Vanda\Schema\Node;
use Vanda\Schema\SchemaContext;
use Vanda\Schema\ValidationScope;

/**
 * A titled block of fields in a frame: the title and description on top inside it, the fields
 * below, the block's own buttons at its bottom.
 *
 *     Layout::section('Profile', 'Name and contacts')->schema([...])->commands([...]),
 */
final class Section extends Node implements ValidationScope
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
     * Buttons under the card. Their actions validate only this section's fields,
     * so one screen can hold several independent forms (profile and password).
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

    public function scopes(Node $button): bool
    {
        return in_array($button, $this->commands, true);
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
