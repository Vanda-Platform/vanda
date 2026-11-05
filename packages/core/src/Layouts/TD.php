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

    public function muted(bool $muted = true): self
    {
        $this->muted = $muted;

        return $this;
    }

    public function name(): string
    {
        return $this->name;
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
            'muted' => $this->muted ?: null,
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
}
