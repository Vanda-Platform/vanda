<?php

declare(strict_types=1);

namespace Vanda\Layouts;

use Closure;
use Illuminate\Contracts\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Vanda\Actions\Button;
use Vanda\Schema\Node;
use Vanda\Schema\SchemaContext;

/**
 * Rows of data with typed columns. Give it a query and it handles search,
 * sorting and pagination from the URL (?q=…&sort=-created_at&page=2):
 *
 *     Layout::table()
 *         ->query(fn () => User::query())
 *         ->searchable(['name', 'email'])
 *         ->paginate(25)
 *         ->columns([TD::make('name', 'Name')->sortable(), TD::make('email', 'Email')])
 *         ->bulkActions([Button::make('Delete')->danger()->action($this->deleteMany(...))])
 *
 * Or pass ready data with ->rows([...]).
 */
final class Table extends Node
{
    /** @var iterable<mixed>|Closure */
    private iterable|Closure $rows = [];

    private ?Closure $query = null;

    /** @var list<string> */
    private array $searchable = [];

    private ?string $searchPlaceholder = null;

    private int $perPage = 25;

    /** @var array{0: string, 1: 'asc'|'desc'}|null */
    private ?array $defaultSort = null;

    /** @var list<TD> */
    private array $columns = [];

    /** @var list<Button> */
    private array $bulkActions = [];

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
     * @param Closure(): EloquentBuilder $query
     */
    public function query(Closure $query): self
    {
        $this->query = $query;

        return $this;
    }

    /**
     * @param list<string> $columns database columns searched with LIKE
     */
    public function searchable(array $columns, ?string $placeholder = null): self
    {
        $this->searchable = array_values($columns);
        $this->searchPlaceholder = $placeholder;

        return $this;
    }

    public function paginate(int $perPage): self
    {
        $this->perPage = $perPage;

        return $this;
    }

    /**
     * @param 'asc'|'desc' $direction
     */
    public function defaultSort(string $column, string $direction = 'asc'): self
    {
        $this->defaultSort = [$column, $direction];

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
     * Buttons for selected rows. Their actions receive `array $ids`.
     *
     * @param list<Button> $buttons
     */
    public function bulkActions(array $buttons): self
    {
        $this->bulkActions = array_map(static fn (Button $button) => $button->asBulk(), array_values($buttons));

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
        [$data, $sort, $search] = $this->load();
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

        $bulk = [];
        foreach ($this->bulkActions as $index => $button) {
            if ($button->isVisible()) {
                $bulk[] = $button->id($this->id.'.bulk.'.$index)->toSchema($context);
            }
        }

        return [
            'columns' => array_map(static fn (TD $column) => $column->header(), $this->columns),
            'rows' => $rows,
            'pagination' => $pagination,
            'sort' => $sort,
            'search' => $this->searchable === [] ? null : ['value' => $search, 'placeholder' => $this->searchPlaceholder],
            'bulk' => $bulk,
            'emptyText' => $this->emptyText,
        ];
    }

    /**
     * @return array{0: iterable<mixed>, 1: ?string, 2: string}
     */
    private function load(): array
    {
        if ($this->query === null) {
            return [$this->rows instanceof Closure ? ($this->rows)() : $this->rows, null, ''];
        }

        $query = ($this->query)();
        $search = trim((string) request()->query('q', ''));

        if ($search !== '' && $this->searchable !== []) {
            $query->where(function ($where) use ($search): void {
                foreach ($this->searchable as $column) {
                    $where->orWhere($column, 'like', '%'.addcslashes($search, '%_\\').'%');
                }
            });
        }

        $sort = (string) request()->query('sort', '');
        $column = ltrim($sort, '-');
        $sortable = array_map(
            static fn (TD $td) => $td->name(),
            array_filter($this->columns, static fn (TD $td) => $td->isSortable()),
        );

        if ($column !== '' && in_array($column, $sortable, true)) {
            $query->orderBy($column, str_starts_with($sort, '-') ? 'desc' : 'asc');
        } elseif ($this->defaultSort !== null) {
            $query->orderBy(...$this->defaultSort);
            $sort = '';
        } else {
            $sort = '';
        }

        return [$query->paginate($this->perPage)->withQueryString(), $sort === '' ? null : $sort, $search];
    }
}
