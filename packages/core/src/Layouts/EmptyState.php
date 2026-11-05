<?php

declare(strict_types=1);

namespace Vanda\Layouts;

use Vanda\Actions\Button;
use Vanda\Actions\Link;
use Vanda\Actions\ModalToggle;
use Vanda\Schema\Node;
use Vanda\Schema\SchemaContext;

/**
 * What to show where there is nothing yet, with a way to start:
 *
 *     EmptyState::make('No orders yet')->icon('file')
 *         ->description('Orders from the shop will appear here.')
 *         ->actions([Link::make('Create order')->primary()->to(OrderEditScreen::class)]),
 *
 * Tables take one with ->empty(EmptyState::make(...)).
 */
final class EmptyState extends Node
{
    private ?string $description = null;

    private ?string $icon = null;

    /** @var list<Button|Link|ModalToggle> */
    private array $actions = [];

    private function __construct(private readonly string $title) {}

    public static function make(string $title): self
    {
        return (new self($title))->captureSource();
    }

    public function type(): string
    {
        return 'empty';
    }

    public function description(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function icon(string $icon): self
    {
        $this->icon = $icon;

        return $this;
    }

    /**
     * @param list<Button|Link|ModalToggle> $actions
     */
    public function actions(array $actions): self
    {
        $this->actions = array_values($actions);

        return $this;
    }

    public function children(): array
    {
        return $this->actions;
    }

    protected function properties(SchemaContext $context): array
    {
        foreach ($this->actions as $index => $action) {
            $action->id ??= $this->id.'.'.$index;
        }

        return ['title' => $this->title, 'description' => $this->description, 'icon' => $this->icon];
    }
}
