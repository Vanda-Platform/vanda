<?php

declare(strict_types=1);

namespace Vanda\Fields;

/**
 * One option of a Select, Radio or filter, when a label alone does not tell options apart:
 * a description is a second, smaller grey line under the label; a hint stays muted on the same line.
 *
 *     Select::make('form.products')->multiple()->searchable()->options(fn () => Product::with('category')->get()
 *         ->map(fn (Product $product) => Option::make($product->id, $product->name)->description($product->category?->name)))
 *
 *     Select::make('form.events')->options(fn () => Event::with('category')->get()
 *         ->map(fn (Event $event) => Option::make($event->id, $event->name)
 *             ->description($event->category?->name.' · '.$event->starts_at->translatedFormat('j F Y'))))
 *
 * The search of a searchable Select finds options by their description too.
 */
final class Option
{
    private ?string $description = null;

    private ?string $hint = null;

    private function __construct(
        private readonly int|string $value,
        private readonly string $label,
    ) {}

    public static function make(int|string $value, string $label): self
    {
        return new self($value, $label);
    }

    /** A second line under the label, small and grey: the category, a date, an email. Empty text adds nothing. */
    public function description(?string $text): self
    {
        $this->description = $text === null || trim($text) === '' ? null : $text;

        return $this;
    }

    /** Muted text after the label on the same line, e.g. "UTC+01:00". */
    public function hint(?string $text): self
    {
        $this->hint = $text === null || trim($text) === '' ? null : $text;

        return $this;
    }

    /**
     * @return array{value: int|string, label: string, hint?: string, description?: string}
     */
    public function toArray(): array
    {
        return array_filter(
            ['value' => $this->value, 'label' => $this->label, 'hint' => $this->hint, 'description' => $this->description],
            static fn ($item) => $item !== null,
        );
    }
}
