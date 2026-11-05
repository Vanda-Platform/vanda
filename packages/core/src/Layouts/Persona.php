<?php

declare(strict_types=1);

namespace Vanda\Layouts;

use Vanda\Schema\Node;
use Vanda\Schema\SchemaContext;

/**
 * A person: initials avatar, name and a second line. Handy in table cells.
 */
final class Persona extends Node
{
    private ?string $subtitle = null;

    private ?string $href = null;

    private ?string $image = null;

    public function __construct(private readonly string $name) {}

    public static function make(string $name): self
    {
        return (new self($name))->captureSource();
    }

    public function type(): string
    {
        return 'persona';
    }

    public function subtitle(?string $subtitle): self
    {
        $this->subtitle = $subtitle;

        return $this;
    }

    /**
     * Photo instead of the initials: `->image($user->avatarUrl())`; null keeps the initials.
     */
    public function image(?string $url): self
    {
        $this->image = $url;

        return $this;
    }

    public function href(string $url): self
    {
        $this->href = $url;

        return $this;
    }

    protected function properties(SchemaContext $context): array
    {
        return [
            'name' => $this->name,
            'subtitle' => $this->subtitle,
            'initials' => Avatar::initials($this->name),
            'hue' => Avatar::hue($this->name),
            'image' => $this->image,
            'href' => $this->href,
        ];
    }
}
