<?php

declare(strict_types=1);

namespace Vanda\Fields;

use Vanda\VandaException;

/**
 * A way to crop an image before it is uploaded. The browser crops, the server receives
 * the result, so nothing else is needed on the server:
 *
 *     Upload::make('form.cover')->image()->crop(
 *         Crop::size(556, 322)->label('Preview'),       // exactly 556×322 pixels
 *         Crop::ratio(1, 1)->label('Square')->maxWidth(1200),
 *     )->customCrop(),                                   // and any proportions the user types
 *
 * The first one is selected when the cropper opens.
 */
final class Crop
{
    private ?string $label = null;

    private ?int $maxWidth = null;

    /** @var array{0: int, 1: int}|null */
    private ?array $minSize = null;

    private function __construct(
        private readonly string $mode,
        private readonly int $width = 0,
        private readonly int $height = 0,
    ) {}

    /**
     * The result is exactly this many pixels (the image is scaled after cropping).
     */
    public static function size(int $width, int $height): self
    {
        self::guard($width, $height, 'size');

        return new self('size', $width, $height);
    }

    /**
     * Keeps these proportions; the size is what the user selected (see maxWidth()).
     */
    public static function ratio(int $width, int $height): self
    {
        self::guard($width, $height, 'ratio');

        return new self('ratio', $width, $height);
    }

    /**
     * Any rectangle the user draws.
     */
    public static function free(): self
    {
        return new self('free');
    }

    public function label(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    /**
     * A wider result is scaled down to this width (for ratio() and free()).
     */
    public function maxWidth(int $pixels): self
    {
        $this->maxWidth = $pixels;

        return $this;
    }

    /**
     * The selected part of the source must be at least this big, so the result is not blurry.
     */
    public function minSize(int $width, int $height): self
    {
        $this->minSize = [$width, $height];

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toSchema(): array
    {
        return array_filter([
            'mode' => $this->mode,
            'width' => $this->width ?: null,
            'height' => $this->height ?: null,
            'label' => $this->label,
            'maxWidth' => $this->maxWidth,
            'minWidth' => $this->minSize[0] ?? null,
            'minHeight' => $this->minSize[1] ?? null,
        ], static fn ($value) => $value !== null);
    }

    private static function guard(int $width, int $height, string $method): void
    {
        if ($width < 1 || $height < 1) {
            throw new VandaException("Crop::{$method}({$width}, {$height}) needs two positive numbers, for example Crop::{$method}(16, 9).");
        }
    }
}
