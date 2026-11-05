<?php

declare(strict_types=1);

namespace Vanda\Layouts;

use Vanda\Schema\Node;
use Vanda\Schema\SchemaContext;

/**
 * Rows of consequential actions separated by rules, edge to edge in a section card,
 * like the "Danger zone" of a user:
 *
 *     Layout::section('Danger zone')->schema([
 *         Layout::actions([
 *             ActionRow::make('Block access')->description('All sessions end at once.')
 *                 ->buttons([Button::make('Block')->action($this->block(...))]),
 *             ActionRow::make('Delete user')->description('Cannot be undone.')
 *                 ->buttons([Button::make('Delete')->danger()->confirm('…')->action($this->delete(...))]),
 *         ]),
 *     ])
 */
final class ActionList extends Node
{
    /**
     * @param list<ActionRow> $rows
     */
    private function __construct(private readonly array $rows) {}

    /**
     * @param list<ActionRow> $rows
     */
    public static function make(array $rows): self
    {
        return (new self(array_values($rows)))->captureSource();
    }

    public function type(): string
    {
        return 'actionlist';
    }

    public function children(): array
    {
        return $this->rows;
    }

    protected function properties(SchemaContext $context): array
    {
        return [];
    }
}
