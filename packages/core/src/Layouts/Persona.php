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

    public function href(string $url): self
    {
        $this->href = $url;

        return $this;
    }

    protected function properties(SchemaContext $context): array
    {
        $initials = implode('', array_map(
            static fn (string $part) => mb_strtoupper(mb_substr($part, 0, 1)),
            array_slice(preg_split('/\s+/u', trim($this->name)) ?: [], 0, 2),
        ));

        return [
            'name' => $this->name,
            'subtitle' => $this->subtitle,
            'initials' => $initials,
            'hue' => crc32($this->name) % 6,
            'href' => $this->href,
        ];
    }
}
