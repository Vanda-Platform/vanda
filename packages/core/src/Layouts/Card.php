<?php

declare(strict_types=1);

namespace Vanda\Layouts;

use Vanda\Schema\Node;
use Vanda\Schema\SchemaContext;
use Vanda\Schema\ValidationScope;

/**
 * A block with its title inside the card, on top of the content, full width:
 *
 *     Layout::card('Questions', 'Shown to clients in this order')
 *         ->commands([ModalToggle::make('Add question')->icon('plus')->modal('question')])
 *         ->schema([$this->questionsTable()]),
 *
 * Unlike Layout::section() (title above the card, buttons at its bottom: forms) a card suits
 * tables, lists and anything wide. Buttons given to ->commands() sit in the card header,
 * next to what they act on, and validate only this card's fields.
 */
final class Card extends Node implements ValidationScope
{
    /** @var list<Node> */
    private array $schema = [];

    /** @var list<Node> */
    private array $commands = [];

    private function __construct(
        private readonly string $title,
        private readonly ?string $description = null,
    ) {}

    public static function make(string $title, ?string $description = null): self
    {
        return (new self($title, $description))->captureSource();
    }

    public function type(): string
    {
        return 'card';
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
     * @param list<Node> $buttons
     */
    public function commands(array $buttons): self
    {
        $this->commands = array_values($buttons);

        return $this;
    }

    public function scopes(Node $button): bool
    {
        return in_array($button, $this->commands, true);
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
