<?php

declare(strict_types=1);

namespace Vanda\Layouts;

use Vanda\Schema\Node;

/**
 * Entry point for layouts, mirroring Orchid's `Layout::` API.
 */
final class Layout
{
    public static function section(string $title, ?string $description = null): Section
    {
        return Section::make($title, $description);
    }

    /**
     * @param list<Node> $nodes
     */
    public static function grid(array $nodes, int $columns = 2): Grid
    {
        return Grid::make($nodes, $columns);
    }

    /**
     * A single column of fields, as in Orchid.
     *
     * @param list<Node> $nodes
     */
    public static function rows(array $nodes): Grid
    {
        return Grid::make($nodes, 1);
    }

    public static function table(): Table
    {
        return Table::make();
    }

    /**
     * @param array<string, list<Node>> $tabs title => nodes
     */
    public static function tabs(array $tabs): Tabs
    {
        return Tabs::make($tabs);
    }

    /**
     * A dialog opened by ModalToggle::make(...)->modal($id).
     */
    public static function modal(string $id, string $title): Modal
    {
        return Modal::make($id, $title);
    }

    /**
     * @param list<Sight> $sights
     */
    public static function legend(mixed $record, array $sights): Legend
    {
        return Legend::make($record, $sights);
    }

    /**
     * @param list<Metric> $metrics
     */
    public static function metrics(array $metrics): Metrics
    {
        return Metrics::make($metrics);
    }
}
