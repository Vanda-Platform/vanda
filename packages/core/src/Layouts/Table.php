<?php

declare(strict_types=1);

namespace Vanda\Layouts;

use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Vanda\Schema\SchemaContext;
use Vanda\Schema\Node;

/**
 * Rows of data with typed columns:
 *
 *     Layout::table()
 *         ->rows(fn () => User::query()->latest()->paginate(25))
 *         ->columns([TD::make('name', 'Name'), TD::make('email', 'Email')->muted()])
 */
final class Table extends Node
{
    /** @var iterable<mixed>|Closure */
    private iterable|Closure $rows = [];

    /** @var list<TD> */
    private array $columns = [];

    private ?Closure $rowLink = null;

    private ?string $emptyText = null;

    public static function make(): self
    {
        return (new self)->captureSource();
    }

    public function type(): string
    {
        return 'table';
    }

    /**
     * Data for the table. A closure is called only when the table is drawn,
     * so reactions that do not touch the table never run its query.
     *
     * @param iterable<mixed>|Closure(): iterable<mixed> $rows
     */
    public function rows(iterable|Closure $rows): self
    {
        $this->rows = $rows;

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

    public function emptyText(string $text): self
    {
        $this->emptyText = $text;

        return $this;
    }

    protected function properties(SchemaContext $context): array
    {
        $data = $this->rows instanceof Closure ? ($this->rows)() : $this->rows;
        $pagination = null;

        if ($data instanceof LengthAwarePaginator) {
            $pagination = [
                'page' => $data->currentPage(),
                'lastPage' => $data->lastPage(),
                'perPage' => $data->perPage(),
                'total' => $data->total(),
                'from' => $data->firstItem(),
                'to' => $data->lastItem(),
            ];
            $data = $data->items();
        }

        $rows = [];
        foreach ($data as $index => $row) {
            $key = (string) (data_get($row, 'id') ?? $index);
            $cells = [];
            foreach ($this->columns as $column) {
                $cells[$column->name()] = $column->cell($row, $context, $this->id.'.'.$key.'.'.$column->name());
            }
            $rows[] = array_filter([
                'key' => $key,
                'link' => $this->rowLink ? ($this->rowLink)($row) : null,
                'cells' => $cells,
            ], static fn ($value) => $value !== null);
        }

        return [
            'columns' => array_map(static fn (TD $column) => $column->header(), $this->columns),
            'rows' => $rows,
            'pagination' => $pagination,
            'emptyText' => $this->emptyText,
        ];
    }
}
