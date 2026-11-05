<?php

declare(strict_types=1);

namespace Vanda\Layouts;

use Vanda\Schema\Node;
use Vanda\Schema\SchemaContext;

final class Text extends Node
{
    private bool $muted = false;

    private bool $mono = false;

    public function __construct(private readonly string $text) {}

    public static function make(string $text): self
    {
        return (new self($text))->captureSource();
    }

    public function type(): string
    {
        return 'text';
    }

    public function muted(bool $muted = true): self
    {
        $this->muted = $muted;

        return $this;
    }

    public function mono(bool $mono = true): self
    {
        $this->mono = $mono;

        return $this;
    }

    protected function properties(SchemaContext $context): array
    {
        return ['text' => $this->text, 'muted' => $this->muted, 'mono' => $this->mono];
    }
}
