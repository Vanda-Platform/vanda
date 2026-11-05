<?php

declare(strict_types=1);

namespace Vanda\Layouts;

use Vanda\Schema\Node;
use Vanda\Schema\SchemaContext;

/**
 * A chart in its own card, drawn as SVG in the browser:
 *
 *     Chart::line('Revenue by day')
 *         ->description('1 684 200 ₽ in 30 days')
 *         ->labels($days)
 *         ->series('This period', $revenue)
 *         ->series('Previous period', $previous)
 *         ->unit('₽')
 *         ->span(2),
 *
 *     Chart::ranking('Top products')->labels($names)->series('Sold', $counts),
 *
 * The first series uses the brand colour, the next ones are muted.
 */
final class Chart extends Node
{
    /** @var list<string> */
    private array $labels = [];

    /** @var list<array{name: string, values: list<float|int>}> */
    private array $series = [];

    private ?string $description = null;

    private ?string $unit = null;

    private int $height = 220;

    private int $span = 1;

    private function __construct(
        private readonly ChartType $kind,
        private readonly string $title,
    ) {}

    public static function line(string $title): self
    {
        return (new self(ChartType::Line, $title))->captureSource();
    }

    public static function bar(string $title): self
    {
        return (new self(ChartType::Bar, $title))->captureSource();
    }

    public static function ranking(string $title): self
    {
        return (new self(ChartType::Ranking, $title))->captureSource();
    }

    public function type(): string
    {
        return 'chart';
    }

    public function description(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Points on the horizontal axis (dates, months) or names in a ranking.
     *
     * @param iterable<string> $labels
     */
    public function labels(iterable $labels): self
    {
        $this->labels = array_values(array_map('strval', is_array($labels) ? $labels : iterator_to_array($labels, false)));

        return $this;
    }

    /**
     * @param iterable<int|float|null> $values one per label
     */
    public function series(string $name, iterable $values): self
    {
        $this->series[] = [
            'name' => $name,
            'values' => array_values(array_map(static fn ($value) => is_int($value) ? $value : (float) $value, is_array($values) ? $values : iterator_to_array($values, false))),
        ];

        return $this;
    }

    /**
     * Shown after values: "₽", "%", "шт.".
     */
    public function unit(string $unit): self
    {
        $this->unit = $unit;

        return $this;
    }

    public function height(int $pixels): self
    {
        $this->height = $pixels;

        return $this;
    }

    /**
     * Columns the card takes inside Layout::grid().
     */
    public function span(int $columns): self
    {
        $this->span = max(1, min(4, $columns));

        return $this;
    }

    protected function properties(SchemaContext $context): array
    {
        return [
            'kind' => $this->kind->value,
            'title' => $this->title,
            'description' => $this->description,
            'labels' => $this->labels,
            'series' => $this->series,
            'unit' => $this->unit,
            'height' => $this->height,
            'span' => $this->span > 1 ? $this->span : null,
        ];
    }
}
