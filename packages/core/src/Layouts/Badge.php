<?php

declare(strict_types=1);

namespace Vanda\Layouts;

use Vanda\Schema\Node;
use Vanda\Schema\SchemaContext;

final class Badge extends Node
{
    private Tone $tone = Tone::Neutral;

    private bool $dot = false;

    public function __construct(private readonly string $label) {}

    public static function make(string $label, Tone $tone = Tone::Neutral): self
    {
        return (new self($label))->tone($tone)->captureSource();
    }

    public function type(): string
    {
        return 'badge';
    }

    public function tone(Tone $tone): self
    {
        $this->tone = $tone;

        return $this;
    }

    /** Status style: a coloured dot before the label. */
    public function dot(bool $dot = true): self
    {
        $this->dot = $dot;

        return $this;
    }

    protected function properties(SchemaContext $context): array
    {
        return [
            'label' => $this->label,
            'tone' => $this->tone === Tone::Neutral ? null : $this->tone->value,
            'dot' => $this->dot,
        ];
    }
}
