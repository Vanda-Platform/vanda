<?php

declare(strict_types=1);

namespace Vanda\Layouts;

/**
 * A person's picture: a photo, or initials on a colour derived from the name,
 * so the same person always gets the same colour.
 *
 *     Avatar::make($user->name)->image($user->attachments('avatar')->first()?->url())
 */
final class Avatar
{
    private ?string $image = null;

    private function __construct(private readonly string $name) {}

    public static function make(string $name): self
    {
        return new self($name);
    }

    public function image(?string $url): self
    {
        $this->image = $url;

        return $this;
    }

    /** "Анна Смирнова" → "АС" */
    public static function initials(string $name): string
    {
        return implode('', array_map(
            static fn (string $part) => mb_strtoupper(mb_substr($part, 0, 1)),
            array_slice(preg_split('/\s+/u', trim($name)) ?: [], 0, 2),
        ));
    }

    /** One of six palette colours, stable for a name. */
    public static function hue(string $name): int
    {
        return crc32($name) % 6;
    }

    /**
     * @return array{name: string, initials: string, hue: int, image?: string}
     */
    public function toArray(): array
    {
        return array_filter([
            'name' => $this->name,
            'initials' => self::initials($this->name),
            'hue' => self::hue($this->name),
            'image' => $this->image,
        ], static fn ($value) => $value !== null);
    }
}
