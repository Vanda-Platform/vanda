<?php

declare(strict_types=1);

namespace Vanda\Layouts;

use Vanda\Actions\Button;
use Vanda\Actions\Link;
use Vanda\Actions\ModalToggle;
use Vanda\Schema\Node;
use Vanda\Schema\SchemaContext;

/**
 * One line of Layout::actions(): what the action does on the left, its button on the right.
 */
final class ActionRow extends Node
{
    private ?string $description = null;

    /** @var list<Button|Link|ModalToggle> */
    private array $buttons = [];

    private function __construct(private readonly string $title) {}

    public static function make(string $title): self
    {
        return (new self($title))->captureSource();
    }

    public function type(): string
    {
        return 'actionrow';
    }

    public function description(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    /**
     * @param list<Button|Link|ModalToggle> $buttons
     */
    public function buttons(array $buttons): self
    {
        $this->buttons = array_values($buttons);

        return $this;
    }

    /**
     * A row whose buttons are all hidden (no permission) is not drawn.
     */
    public function isVisible(): bool
    {
        return parent::isVisible() && array_filter($this->buttons, static fn (Node $button) => $button->isVisible()) !== [];
    }

    public function children(): array
    {
        return $this->buttons;
    }

    protected function properties(SchemaContext $context): array
    {
        return ['title' => $this->title, 'description' => $this->description];
    }
}
