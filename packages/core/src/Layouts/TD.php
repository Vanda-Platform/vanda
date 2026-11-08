<?php

declare(strict_types=1);

namespace Vanda\Layouts;

use Closure;
use Vanda\Schema\Node;
use Vanda\Schema\SchemaContext;

/**
 * A table column. The cell shows `data_get($row, $name)` unless ->render() is given.
 */
final class TD
{
    private ?Closure $render = null;

    private string $align = 'left';

    private ?string $width = null;

    private bool $muted = false;

    private ?string $truncate = null;

    private bool $sortable = false;

    /** null: always shown; true/false: the user may hide it, shown by default or not */
    private ?bool $shownByDefault = null;

    private bool $onPhones = true;

    private function __construct(
        private readonly string $name,
        private readonly string $label,
    ) {}

    public static function make(string $name, ?string $label = null): self
    {
        return new self($name, $label ?? $name);
    }

    /**
     * @param Closure(mixed $row): (Node|string|int|float|null) $render
     */
    public function render(Closure $render): self
    {
        $this->render = $render;

        return $this;
    }

    public function alignRight(): self
    {
        $this->align = 'right';

        return $this;
    }

    public function alignCenter(): self
    {
        $this->align = 'center';

        return $this;
    }

    public function width(string $width): self
    {
        $this->width = $width;

        return $this;
    }

    /**
     * Long text ends with "…" at this width instead of widening the table; the full text shows
     * on hover. The first column is held to 40% of the table anyway, so it never covers the rest.
     *
     *     TD::make('company', 'Компания')->truncate()
     *     TD::make('comment', 'Комментарий')->truncate('24rem')
     *
     * @param string $maxWidth a CSS length
     */
    public function truncate(string $maxWidth = '16rem'): self
    {
        $this->truncate = $maxWidth;

        return $this;
    }

    public function muted(bool $muted = true): self
    {
        $this->muted = $muted;

        return $this;
    }

    /**
     * Header becomes a sort toggle (?sort=name / ?sort=-name). Works with
     * Table::query(); the column name must be a database column.
     */
    public function sortable(bool $sortable = true): self
    {
        $this->sortable = $sortable;

        return $this;
    }

    /**
     * The user may hide this column from the "Columns" menu; the choice is remembered in the browser.
     */
    public function hideable(): self
    {
        $this->shownByDefault ??= true;

        return $this;
    }

    /**
     * Hidden until the user turns it on in the "Columns" menu.
     */
    public function defaultHidden(): self
    {
        $this->shownByDefault = false;

        return $this;
    }

    /**
     * Left out of the row cards on phones, where every column becomes a line of the card;
     * for details that only matter on a wide screen:
     *
     *     TD::make('email', 'Email')->hideOnMobile()
     */
    public function hideOnMobile(bool $hide = true): self
    {
        $this->onPhones = ! $hide;

        return $this;
    }

    public function isSortable(): bool
    {
        return $this->sortable;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function label(): string
    {
        return $this->label;
    }

    /**
     * @return array<string, mixed>
     */
    public function header(): array
    {
        return array_filter([
            'name' => $this->name,
            'label' => $this->label,
            'align' => $this->align === 'left' ? null : $this->align,
            'width' => $this->width,
            'truncate' => $this->truncate,
            'muted' => $this->muted ?: null,
            'sortable' => $this->sortable ?: null,
            'hideable' => $this->shownByDefault === null ? null : true,
            'hidden' => $this->shownByDefault === false ? true : null,
            'mobile' => $this->onPhones ? null : false,
        ], static fn ($value) => $value !== null);
    }

    /**
     * @return array<string, mixed>
     */
    public function cell(mixed $row, SchemaContext $context, string $id): array
    {
        $value = $this->render ? ($this->render)($row) : data_get($row, $this->name);

        if ($value instanceof Node) {
            return $value->id($value->getId() ?? $id)->toSchema($context);
        }

        if ($value instanceof \BackedEnum) {
            $value = $value->value;
        } elseif ($value instanceof \DateTimeInterface) {
            $value = $value->format('d.m.Y H:i');
        }

        return ['type' => 'text', 'text' => $value === null ? '' : (string) $value];
    }

    /**
     * Plain text of the cell for CSV export: the text a node shows, or the raw value.
     */
    public function text(mixed $row, SchemaContext $context): string
    {
        $cell = $this->cell($row, $context, 'export');

        return (string) ($cell['text'] ?? $cell['label'] ?? $cell['name'] ?? '');
    }
}
