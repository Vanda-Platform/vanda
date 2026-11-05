<?php

declare(strict_types=1);

namespace Vanda\Layouts;

use Vanda\Schema\Node;
use Vanda\Schema\SchemaContext;

/**
 * An inline message on the screen: Alert::make('12 products have no price.')->tone(Tone::Warning)
 */
final class Alert extends Node
{
    private Tone $tone = Tone::Accent;

    private ?string $title = null;

    public function __construct(private readonly string $text) {}

    public static function make(string $text): self
    {
        return (new self($text))->captureSource();
    }

    public function type(): string
    {
        return 'alert';
    }

    public function tone(Tone $tone): self
    {
        $this->tone = $tone;

        return $this;
    }

    public function title(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    protected function properties(SchemaContext $context): array
    {
        return ['text' => $this->text, 'title' => $this->title, 'tone' => $this->tone->value];
    }
}
