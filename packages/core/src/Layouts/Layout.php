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
     * Server-rendered HTML from a Blade view (see BladeView):
     *
     *     Layout::view('admin.orders.summary', fn () => ['orders' => $this->orders]),
     *
     * @param array<string, mixed>|\Closure(): array<string, mixed> $data
     */
    public static function view(string $view, array|\Closure $data = []): BladeView
    {
        return BladeView::make($view, $data);
    }

    /**
     * A full-width block: title on top, content below, buttons in the header.
     */
    public static function card(string $title, ?string $description = null): Card
    {
        return Card::make($title, $description);
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
     * Nested records with expand/collapse and drag-and-drop ordering.
     */
    public static function tree(): TreeTable
    {
        return TreeTable::make();
    }

    /**
     * Rows "what it does — button", separated by rules (a user's danger zone).
     *
     * @param list<ActionRow> $rows
     */
    public static function actions(array $rows): ActionList
    {
        return ActionList::make($rows);
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
