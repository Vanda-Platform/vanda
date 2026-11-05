<?php

declare(strict_types=1);

namespace Vanda\Layouts;

use Closure;
use Vanda\Schema\Node;
use Vanda\Schema\SchemaContext;

/**
 * One row of Layout::legend(): a label and a value of the record.
 */
final class Sight
{
    private ?Closure $render = null;

    private bool $copyable = false;

    private bool $mono = false;

    private function __construct(
        private readonly string $name,
        private readonly string $label,
    ) {}

    public static function make(string $name, ?string $label = null): self
    {
        return new self($name, $label ?? $name);
    }

    /**
     * @param Closure(mixed $record): (Node|string|int|float|null) $render
     */
    public function render(Closure $render): self
    {
        $this->render = $render;

        return $this;
    }

    /** Adds a copy-to-clipboard button. */
    public function copyable(bool $copyable = true): self
    {
        $this->copyable = $copyable;

        return $this;
    }

    public function mono(bool $mono = true): self
    {
        $this->mono = $mono;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(mixed $record, SchemaContext $context, string $id): array
    {
        $value = $this->render ? ($this->render)($record) : data_get($record, $this->name);

        if ($value instanceof Node) {
            $value = $value->id($value->getId() ?? $id)->toSchema($context);
        } else {
            $value = ['type' => 'text', 'text' => match (true) {
                $value instanceof \BackedEnum => (string) $value->value,
                $value instanceof \DateTimeInterface => $value->format('d.m.Y H:i'),
                default => $value === null || $value === '' ? '—' : (string) $value,
            }, 'mono' => $this->mono ?: null];
        }

        return array_filter([
            'label' => $this->label,
            'value' => array_filter($value, static fn ($v) => $v !== null),
            'copyable' => $this->copyable,
        ]);
    }
}
