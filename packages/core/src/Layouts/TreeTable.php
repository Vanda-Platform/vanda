<?php

declare(strict_types=1);

namespace Vanda\Layouts;

use Closure;
use Illuminate\Contracts\Database\Eloquent\Builder as EloquentBuilder;
use Vanda\Actions\Button;
use Vanda\Actions\DropDown;
use Vanda\Actions\Link;
use Vanda\Actions\ModalToggle;
use Vanda\Schema\Node;
use Vanda\Schema\SchemaContext;
use Vanda\Screen\ActionCall;

/**
 * A table of nested records (pages, categories, menu items) stored as an adjacency list:
 *
 *     Layout::tree()
 *         ->query(fn () => Page::query())
 *         ->parentKey('parent_id')
 *         ->orderBy('position')
 *         ->columns([TD::make('title', 'Title'), TD::make('path', 'Address')->muted()])
 *         ->rowLink(fn (Page $page) => Panel::url(PageEditScreen::class, ['page' => $page]))
 *         ->reorderable($this->move(...))
 *
 * The first column holds the expand toggle. With ->reorderable() rows can be dragged
 * (or moved with Alt+arrows); the action receives `$id`, `$parent` and `$position`
 * (0-based among the new siblings) and is expected to save the new order.
 */
final class TreeTable extends Node
{
    /** @var iterable<mixed>|Closure */
    private iterable|Closure $items = [];

    private ?Closure $query = null;

    private string $key = 'id';

    private string $parentKey = 'parent_id';

    private ?string $orderBy = null;

    /** @var list<TD> */
    private array $columns = [];

    private ?Closure $rowLink = null;

    private ?Closure $rowActions = null;

    private ?Closure $reorder = null;

    private int $expandDepth = 1;

    private bool $searchable = false;

    private ?string $emptyText = null;

    public static function make(): self
    {
        return (new self)->captureSource();
    }

    public function type(): string
    {
        return 'treetable';
    }

    /**
     * @param Closure(): EloquentBuilder $query
     */
    public function query(Closure $query): self
    {
        $this->query = $query;

        return $this;
    }

    /**
     * Ready records instead of a query.
     *
     * @param iterable<mixed>|Closure(): iterable<mixed> $items
     */
    public function items(iterable|Closure $items): self
    {
        $this->items = $items;

        return $this;
    }

    public function key(string $key): self
    {
        $this->key = $key;

        return $this;
    }

    public function parentKey(string $parentKey): self
    {
        $this->parentKey = $parentKey;

        return $this;
    }

    /**
     * Column that orders siblings.
     */
    public function orderBy(string $column): self
    {
        $this->orderBy = $column;

        return $this;
    }

    /**
     * @param list<TD> $columns
     */
    public function columns(array $columns): self
    {
        $this->columns = array_values($columns);

        return $this;
    }

    /**
     * @param Closure(mixed $row): ?string $link
     */
    public function rowLink(Closure $link): self
    {
        $this->rowLink = $link;

        return $this;
    }

    /**
     * @param Closure(mixed $row): list<Button|Link|ModalToggle> $actions
     */
    public function rowActions(Closure $actions): self
    {
        $this->rowActions = $actions;

        return $this;
    }

    /**
     * Rows can be dragged; the #[Action] receives `$id`, `$parent` (null for the top level)
     * and `$position`.
     */
    public function reorderable(Closure $move): self
    {
        $this->reorder = $move;

        return $this;
    }

    /**
     * Levels open on the first visit (0 = everything collapsed). Later the browser
     * remembers what the user opened.
     */
    public function expanded(int $depth): self
    {
        $this->expandDepth = $depth;

        return $this;
    }

    /**
     * A search box that filters rows by the first column, keeping their ancestors visible.
     */
    public function searchable(bool $searchable = true): self
    {
        $this->searchable = $searchable;

        return $this;
    }

    public function emptyText(string $text): self
    {
        $this->emptyText = $text;

        return $this;
    }

    protected function properties(SchemaContext $context): array
    {
        $records = $this->records();
        $byParent = [];
        $keys = [];

        foreach ($records as $record) {
            $keys[(string) data_get($record, $this->key)] = true;
        }

        foreach ($records as $record) {
            $parent = data_get($record, $this->parentKey);
            $parent = $parent === null || ! isset($keys[(string) $parent]) ? '' : (string) $parent;
            $byParent[$parent][] = $record;
        }

        $rows = [];
        $walk = function (string $parent, int $depth) use (&$walk, &$rows, $byParent, $context): void {
            foreach ($byParent[$parent] ?? [] as $record) {
                $key = (string) data_get($record, $this->key);
                $cells = [];
                foreach ($this->columns as $column) {
                    $cells[$column->name()] = $column->cell($record, $context, $this->id.'.'.$key.'.'.$column->name());
                }

                $actions = null;
                if ($this->rowActions !== null) {
                    $menu = DropDown::make()->icon('more')->ghost()->list(($this->rowActions)($record))->id($this->id.'.'.$key.'.actions');
                    $actions = $menu->isVisible() ? $menu->toSchema($context) : null;
                }

                $rows[] = array_filter([
                    'key' => $key,
                    'parent' => $parent === '' ? null : $parent,
                    'depth' => $depth,
                    'link' => $this->rowLink ? ($this->rowLink)($record) : null,
                    'cells' => $cells,
                    'actions' => $actions,
                ], static fn ($value) => $value !== null);

                $walk($key, $depth + 1);
            }
        };
        $walk('', 0);

        return [
            'columns' => array_map(static fn (TD $column) => $column->header(), $this->columns),
            'rows' => $rows,
            'expandDepth' => $this->expandDepth,
            'searchable' => $this->searchable,
            'move' => $this->reorder === null ? null : ActionCall::withInput($this->reorder, ['id', 'parent', 'position']),
            'emptyText' => $this->emptyText,
        ];
    }

    /**
     * @return list<mixed>
     */
    private function records(): array
    {
        if ($this->query !== null) {
            $query = ($this->query)();
            if ($this->orderBy !== null) {
                $query->orderBy($this->orderBy);
            }

            return $query->get()->all();
        }

        $items = $this->items instanceof Closure ? ($this->items)() : $this->items;
        $items = is_array($items) ? array_values($items) : iterator_to_array($items, false);

        if ($this->orderBy !== null) {
            usort($items, fn ($a, $b) => data_get($a, $this->orderBy) <=> data_get($b, $this->orderBy));
        }

        return $items;
    }
}
