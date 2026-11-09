<?php

declare(strict_types=1);

namespace Vanda\Layouts;

use Closure;
use Illuminate\Contracts\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Vanda\Actions\Button;
use Vanda\Actions\DropDown;
use Vanda\Actions\Link;
use Vanda\Actions\ModalToggle;
use Vanda\Layouts\Concerns\OpensRows;
use Vanda\Schema\Node;
use Vanda\Schema\SchemaContext;
use Vanda\Support\Preferences;
use Vanda\Support\Search;

/**
 * Rows of data with typed columns. Give it a query and it handles search, filters,
 * sorting and pagination from the URL (?q=…&filter[role]=admin&sort=-created_at&page=2):
 *
 *     Layout::table()
 *         ->query(fn () => User::query())
 *         ->searchable(['name', 'email'])
 *         ->filters([Filter::select('status', 'Status')->options(UserStatus::class)])
 *         ->views([View::make('All'), View::make('Blocked', ['status' => 'blocked'])])
 *         ->paginate(25, [10, 25, 50])
 *         ->columns([TD::make('name', 'Name')->sortable(), TD::make('email', 'Email')])
 *         ->rowLink(fn (User $user) => Panel::url(UserEditScreen::class, ['user' => $user]))
 *         ->rowActions(fn (User $user) => [Link::make('Edit')->to(UserEditScreen::class, ['user' => $user])])
 *         ->bulkActions([Button::make('Delete')->danger()->action($this->deleteMany(...))])
 *         ->exportable('users.csv')
 *
 * Or pass ready data with ->rows([...]). A click on a row opens ->rowLink() or runs ->rowAction()
 * (a modal for the row, say): ->rowAction(fn (User $user) => ModalToggle::make('Open')->modal('quick')->load(…)).
 */
final class Table extends Node
{
    use OpensRows;

    /** @var iterable<mixed>|Closure */
    private iterable|Closure $rows = [];

    private ?Closure $query = null;

    /** @var list<string> */
    private array $searchable = [];

    private ?string $searchPlaceholder = null;

    private int $perPage = 25;

    /** @var list<int> */
    private array $perPageOptions = [];

    /** @var array{0: string, 1: 'asc'|'desc'}|null */
    private ?array $defaultSort = null;

    /** @var list<TD> */
    private array $columns = [];

    /** @var list<Filter> */
    private array $filters = [];

    /** @var list<View> */
    private array $views = [];

    /** @var list<Button> */
    private array $bulkActions = [];

    /** @var list<Node> */
    private array $commands = [];

    private ?Closure $rowActions = null;

    private ?string $exportFile = null;

    private ?string $emptyText = null;

    private ?EmptyState $empty = null;

    private bool $remember = true;

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
     * The search box finds rows where any of these columns contains the text, whatever its case
     * and alphabet (Search::apply()).
     *
     *     ->searchable(['name', 'email'], 'Имя или email')
     *
     * @param list<string> $columns database columns
     */
    public function searchable(array $columns, ?string $placeholder = null): self
    {
        $this->searchable = array_values($columns);
        $this->searchPlaceholder = $placeholder;

        return $this;
    }

    /**
     * @param list<int> $options page sizes the user may pick (?perPage=50); empty hides the picker
     */
    public function paginate(int $perPage, array $options = []): self
    {
        $this->perPage = $perPage;
        $this->perPageOptions = $options === [] ? [] : array_values(array_unique([...$options, $perPage]));
        sort($this->perPageOptions);

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
     * @param list<Filter> $filters
     */
    public function filters(array $filters): self
    {
        $this->filters = array_values($filters);

        return $this;
    }

    /**
     * Tabs with preset filter values and row counts. The tab whose filters match the URL is active.
     *
     * @param list<View> $views
     */
    public function views(array $views): self
    {
        $this->views = array_values($views);

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
     * Buttons on the table's toolbar, next to search and export: "Add question" belongs
     * to the questions table, not to the screen header.
     *
     * @param list<Button|Link|ModalToggle|DropDown> $buttons
     */
    public function commands(array $buttons): self
    {
        $this->commands = array_values($buttons);

        return $this;
    }

    public function children(): array
    {
        return $this->commands;
    }

    /**
     * A "⋯" menu at the end of every row.
     *
     * @param Closure(mixed $row): list<Button|Link|ModalToggle> $actions
     */
    public function rowActions(Closure $actions): self
    {
        $this->rowActions = $actions;

        return $this;
    }

    /**
     * Adds an "Export" link that downloads every filtered row as CSV
     * (UTF-8 with BOM and ";" separators, so Excel opens it as is).
     */
    public function exportable(string $filename = 'export.csv'): self
    {
        $this->exportFile = $filename;

        return $this;
    }

    public function isExportable(): bool
    {
        return $this->exportFile !== null && $this->query !== null;
    }

    /**
     * Whether the table keeps the user's view (search, filters, sort, rows per page, page) in their
     * account and brings it back when they return to the screen (on by default; see RememberedView).
     */
    public function remember(bool $remember = true): self
    {
        $this->remember = $remember;

        return $this;
    }

    /**
     * @internal A table of a query whose view is remembered.
     */
    public function remembers(): bool
    {
        return $this->remember && $this->query !== null;
    }

    /**
     * The parameters of the table's view in `$raw` (a URL's query or a saved view) that this table
     * accepts, cleaned as they are when read from the URL: a search of a searchable table, filter
     * values a filter takes, a sortable column, a page size it offers, a page after the first.
     *
     * @internal
     * @param array<string, mixed> $raw
     * @return array<string, mixed> in the URL's shape: ['q' => …, 'filter' => [name => value], 'sort' => …, 'perPage' => …, 'page' => …]
     */
    public function viewOf(array $raw): array
    {
        $view = [];

        $search = is_scalar($raw['q'] ?? null) ? trim((string) $raw['q']) : '';
        if ($search !== '' && $this->searchable !== []) {
            $view['q'] = mb_substr($search, 0, 200);
        }

        $filters = $this->filterValues(is_array($raw['filter'] ?? null) ? $raw['filter'] : []);
        foreach ($filters as $name => $value) {
            $view['filter'][$name] = is_array($value) ? array_filter($value, static fn ($part) => $part !== null) : $value;
        }

        $sort = is_string($raw['sort'] ?? null) ? $raw['sort'] : '';
        if ($this->isSortable($sort)) {
            $view['sort'] = $sort;
        }

        $perPage = is_scalar($raw['perPage'] ?? null) ? (string) $raw['perPage'] : '';
        if (ctype_digit($perPage) && in_array((int) $perPage, $this->perPageOptions, true)) {
            $view['perPage'] = (string) (int) $perPage;
        }

        $page = is_scalar($raw['page'] ?? null) ? (string) $raw['page'] : '';
        if (ctype_digit($page) && (int) $page > 1) {
            $view['page'] = (string) (int) $page;
        }

        return $view;
    }

    /**
     * Where a user's hidden columns of this table are kept (Vanda\Support\Preferences): per screen
     * and table, not per route parameters, since the columns are the same in every folder.
     */
    public function columnsKey(string $screen): string
    {
        return 'columns:'.$screen.':'.$this->id;
    }

    /**
     * @return list<string> names of the columns the user may hide or show
     */
    public function hideableColumns(): array
    {
        return array_values(array_map(static fn (TD $column) => $column->name(), array_filter($this->columns, static fn (TD $column) => $column->isHideable())));
    }

    /**
     * The headers of the columns, hidden as the signed-in user chose (TD::defaultHidden() for a user
     * who never did), and whether they chose: 'saved', 'none', or null for a guest.
     *
     * @return array{0: list<array<string, mixed>>, 1: ?string}
     */
    private function columnHeaders(SchemaContext $context): array
    {
        $headers = array_map(static fn (TD $column) => $column->header(), $this->columns);
        $hideable = $this->hideableColumns();
        $user = $hideable === [] ? null : Auth::guard(config('vanda.guard'))->user();

        if ($user === null) {
            return [$headers, null];
        }

        $saved = Preferences::get($user, $this->columnsKey($context->screen::class));
        if (! is_array($saved['hidden'] ?? null)) {
            return [$headers, 'none'];
        }

        $headers = array_map(static fn (TD $column) => $column->header(in_array($column->name(), $saved['hidden'], true)), $this->columns);

        return [$headers, 'saved'];
    }

    public function emptyText(string $text): self
    {
        $this->emptyText = $text;

        return $this;
    }

    /**
     * Shown instead of the rows when there are none (and no search or filter hides them).
     */
    public function empty(EmptyState $empty): self
    {
        $this->empty = $empty;

        return $this;
    }

    protected function properties(SchemaContext $context): array
    {
        [$data, $sort, $search, $filters] = $this->load();
        [$columns, $columnChoice] = $this->columnHeaders($context);
        $pagination = null;

        if ($data instanceof LengthAwarePaginator) {
            $pagination = [
                'page' => $data->currentPage(),
                'lastPage' => $data->lastPage(),
                'perPage' => $data->perPage(),
                'total' => $data->total(),
                'from' => $data->firstItem(),
                'to' => $data->lastItem(),
                'options' => $this->perPageOptions === [] ? null : $this->perPageOptions,
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

            $actions = null;
            if ($this->rowActions !== null) {
                $menu = DropDown::make()->icon('more')->ghost()->list(($this->rowActions)($row))->id($this->id.'.'.$key.'.actions');
                $actions = $menu->isVisible() ? $menu->toSchema($context) : null;
            }

            $rows[] = array_filter([
                'key' => $key,
                ...$this->rowOpens($row, $key, $context),
                'cells' => $cells,
                'actions' => $actions,
            ], static fn ($value) => $value !== null);
        }

        $bulk = [];
        foreach ($this->bulkActions as $index => $button) {
            if ($button->isVisible()) {
                $bulk[] = $button->id($this->id.'.bulk.'.$index)->toSchema($context);
            }
        }

        return [
            'columns' => $columns,
            // Whether the signed-in user chose the hidden columns already ('saved' / 'none'); a guest's stay in the browser.
            'columnChoice' => $columnChoice,
            'rows' => $rows,
            'commandIds' => array_map(static fn (Node $node) => $node->getId(), array_filter($this->commands, static fn (Node $node) => $node->isVisible())),
            'pagination' => $pagination,
            'sort' => $sort,
            'search' => $this->searchable === [] ? null : ['value' => $search, 'placeholder' => $this->searchPlaceholder],
            'filters' => array_map(static fn (Filter $filter) => $filter->toArray($filters[$filter->name()] ?? null), $this->filters),
            'views' => $this->viewsSchema($filters),
            'bulk' => $bulk,
            'export' => $this->isExportable() ? $this->id : null,
            'emptyText' => $this->emptyText,
            'empty' => $this->empty !== null && $rows === [] && $search === '' && $filters === [] ? $this->empty->id($this->id.'.empty')->toSchema($context) : null,
        ];
    }

    /**
     * Every filtered and sorted row as CSV, columns as on screen.
     */
    public function export(SchemaContext $context): StreamedResponse
    {
        $query = $this->filteredQuery();
        $columns = $this->columns;

        return response()->streamDownload(static function () use ($query, $columns, $context): void {
            $out = fopen('php://output', 'wb');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, array_map(static fn (TD $column) => $column->label(), $columns), ';', escape: '');
            foreach ($query->lazy(500) as $row) {
                fputcsv($out, array_map(static fn (TD $column) => $column->text($row, $context), $columns), ';', escape: '');
            }
            fclose($out);
        }, (string) $this->exportFile, ['Content-Type' => 'text/csv; charset=utf-8']);
    }

    /**
     * @return array{0: iterable<mixed>, 1: ?string, 2: string, 3: array<string, mixed>}
     */
    private function load(): array
    {
        if ($this->query === null) {
            return [$this->rows instanceof Closure ? ($this->rows)() : $this->rows, null, '', []];
        }

        $sort = (string) request()->query('sort', '');
        $perPage = (int) request()->query('perPage', (string) $this->perPage);

        if (! in_array($perPage, $this->perPageOptions, true)) {
            $perPage = $this->perPage;
        }

        $page = $this->filteredQuery()->paginate($perPage)->withQueryString();
        // A remembered page past the end (rows were deleted meanwhile) shows the last one instead.
        if ($page->currentPage() > $page->lastPage() && $page->total() > 0) {
            $page = $this->filteredQuery()->paginate($perPage, ['*'], 'page', $page->lastPage())->withQueryString();
        }

        return [
            $page,
            $this->isSortable($sort) ? $sort : null,
            trim((string) request()->query('q', '')),
            $this->filterValues((array) request()->query('filter', [])),
        ];
    }

    /**
     * The query with search, filters and sorting from the URL applied.
     */
    private function filteredQuery(): EloquentBuilder
    {
        $query = ($this->query)();
        $search = trim((string) request()->query('q', ''));

        Search::apply($query, $this->searchable, $search);

        $this->applyFilters($query, $this->filterValues((array) request()->query('filter', [])));

        $sort = (string) request()->query('sort', '');
        if ($this->isSortable($sort)) {
            $query->orderBy(ltrim($sort, '-'), str_starts_with($sort, '-') ? 'desc' : 'asc');
        } elseif ($this->defaultSort !== null) {
            $query->orderBy(...$this->defaultSort);
        }

        return $query;
    }

    private function isSortable(string $sort): bool
    {
        $column = ltrim($sort, '-');

        foreach ($this->columns as $td) {
            if ($td->isSortable() && $td->name() === $column) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $raw
     * @return array<string, mixed> only the filters that are set, with clean values
     */
    private function filterValues(array $raw): array
    {
        $values = [];
        foreach ($this->filters as $filter) {
            $value = $filter->value($raw);
            if ($value !== null) {
                $values[$filter->name()] = $value;
            }
        }

        return $values;
    }

    /**
     * @param array<string, mixed> $values
     */
    private function applyFilters(EloquentBuilder $query, array $values): void
    {
        foreach ($this->filters as $filter) {
            if (isset($values[$filter->name()])) {
                $filter->applyTo($query, $values[$filter->name()]);
            }
        }
    }

    /**
     * @param array<string, mixed> $current
     * @return list<array<string, mixed>>
     */
    private function viewsSchema(array $current): array
    {
        $views = [];
        foreach ($this->views as $view) {
            $values = $this->filterValues($view->filters());
            $count = null;

            if ($this->query !== null) {
                $query = ($this->query)();
                $this->applyFilters($query, $values);
                $count = $query->count();
            }

            $views[] = array_filter([
                'label' => $view->label(),
                'filters' => $values,
                'count' => $count,
                'active' => $values == $current,
            ], static fn ($value) => $value !== null && $value !== false);
        }

        return $views;
    }
}
