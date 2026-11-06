<?php

declare(strict_types=1);

namespace Vanda\Layouts;

use Vanda\Actions\Button;
use Vanda\Schema\Node;
use Vanda\Schema\SchemaContext;
use Vanda\Schema\ValidationScope;

/**
 * A dialog opened by ModalToggle. A button in its footer validates only the
 * fields inside the modal, so the rest of the screen does not block it:
 *
 *     Layout::modal('quickEdit', 'Quick edit')
 *         ->schema([Input::make('quick.name')->label('Name')])
 *         ->buttons([Button::make('Save')->primary()->action($this->saveQuick(...))])
 */
final class Modal extends Node implements ValidationScope
{
    /** @var list<Node> */
    private array $schema = [];

    /** @var list<Button> */
    private array $buttons = [];

    private ?string $description = null;

    private string $size = 'md';

    public function __construct(string $id, private readonly string $title)
    {
        $this->id = $id;
    }

    public static function make(string $id, string $title): self
    {
        return (new self($id, $title))->captureSource();
    }

    public function type(): string
    {
        return 'modal';
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
     * @param list<Button> $buttons footer buttons; "Cancel" is added automatically
     */
    public function buttons(array $buttons): self
    {
        $this->buttons = array_values($buttons);

        return $this;
    }

    public function description(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function wide(): self
    {
        $this->size = 'lg';

        return $this;
    }

    public function children(): array
    {
        return [...$this->schema, ...$this->buttons];
    }

    public function scopes(Node $button): bool
    {
        return true;
    }

    protected function properties(SchemaContext $context): array
    {
        return [
            'title' => $this->title,
            'description' => $this->description,
            'size' => $this->size === 'md' ? null : $this->size,
            'buttonIds' => array_map(static fn (Button $button) => $button->getId(), $this->buttons),
        ];
    }
}
