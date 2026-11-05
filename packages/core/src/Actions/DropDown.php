<?php

declare(strict_types=1);

namespace Vanda\Actions;

use Vanda\Schema\Node;
use Vanda\Schema\SchemaContext;

/**
 * A menu of buttons, links and modal toggles:
 *
 *     DropDown::make('Actions')->icon('more')->list([
 *         Link::make('Edit')->icon('pencil')->to(UserEditScreen::class, ['user' => $user]),
 *         Button::make('Delete')->danger()->confirm('The user will be deleted.')->action($this->delete(...), ['id' => $user->id]),
 *     ])
 *
 * Items the user may not use are left out; a menu with no items is not drawn.
 */
final class DropDown extends Node
{
    /** @var list<Node> */
    private array $items = [];

    private Variant $variant = Variant::Secondary;

    private ?string $icon = null;

    private function __construct(private readonly string $label) {}

    public static function make(string $label = ''): self
    {
        return (new self($label))->captureSource();
    }

    public function type(): string
    {
        return 'dropdown';
    }

    /**
     * @param list<Button|Link|ModalToggle> $items
     */
    public function list(array $items): self
    {
        $this->items = array_values($items);

        return $this;
    }

    public function icon(string $icon): self
    {
        $this->icon = $icon;

        return $this;
    }

    public function variant(Variant $variant): self
    {
        $this->variant = $variant;

        return $this;
    }

    public function ghost(): self
    {
        return $this->variant(Variant::Ghost);
    }

    public function isVisible(): bool
    {
        return parent::isVisible() && $this->visibleItems() !== [];
    }

    public function children(): array
    {
        return $this->items;
    }

    protected function properties(SchemaContext $context): array
    {
        // Items of a menu built inside a table cell are not indexed by the Tree; name them after the menu.
        foreach ($this->items as $index => $item) {
            $item->id ??= $this->id.'.'.$index;
        }

        return [
            'label' => $this->label,
            'icon' => $this->icon,
            'variant' => $this->variant->value,
        ];
    }

    /**
     * @return list<Node>
     */
    private function visibleItems(): array
    {
        return array_values(array_filter($this->items, static fn (Node $item) => $item->isVisible()));
    }
}
