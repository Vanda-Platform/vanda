<?php

declare(strict_types=1);

namespace Vanda\Fields;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Vanda\Schema\Get;
use Vanda\Schema\SchemaContext;
use Vanda\VandaException;

/**
 * Choosing records from a table too big for a Select: the browser asks the server as the user
 * types (Orchid's Relation). The value is the record key, or a list of keys with ->multiple():
 *
 *     Relation::make('form.client_id')->label('Client')->model(Client::class, 'name')
 *         ->searchColumns('name', 'email', 'phone')
 *         ->display(fn (Client $client) => "{$client->name}, {$client->phone}"),
 *
 *     // a narrowed query, which may read other fields of the form
 *     Relation::make('form.office_id')->query(fn (Get $get) => Office::query()->where('city_id', $get('form.city_id')))
 *
 * Only records of the query are accepted, on every validating action.
 */
final class Relation extends Field
{
    /** @var class-string<Model>|null */
    private ?string $model = null;

    private ?Closure $query = null;

    private string $labelColumn = 'name';

    /** @var list<string> */
    private array $searchColumns = [];

    private ?Closure $display = null;

    private bool $multiple = false;

    private int $limit = 20;

    public function type(): string
    {
        return 'relation';
    }

    /**
     * @param class-string<Model> $model
     * @param string $label column shown and searched
     */
    public function model(string $model, string $label = 'name'): static
    {
        $this->model = $model;
        $this->labelColumn = $label;

        return $this;
    }

    /**
     * The records to choose from: scopes, filters, even other fields of the form.
     *
     * @param Closure(Get): Builder<Model> $query
     */
    public function query(Closure $query): static
    {
        $this->query = $query;

        return $this;
    }

    /**
     * Columns the typed text is looked for in (the label column by default).
     */
    public function searchColumns(string ...$columns): static
    {
        $this->searchColumns = array_values($columns);

        return $this;
    }

    /**
     * The text of an option, when one column is not enough.
     *
     * @param Closure(Model): string $label
     */
    public function display(Closure $label): static
    {
        $this->display = $label;

        return $this;
    }

    public function multiple(bool $multiple = true): static
    {
        $this->multiple = $multiple;

        return $this;
    }

    public function isMultiple(): bool
    {
        return $this->multiple;
    }

    /**
     * How many matches the browser gets at once (20).
     */
    public function limit(int $count): static
    {
        $this->limit = $count;

        return $this;
    }

    /**
     * Records matching what the user typed; an empty text gives the first ones.
     *
     * @return list<array{value: int|string, label: string}>
     */
    public function search(string $term, Get $get): array
    {
        $query = $this->baseQuery($get);
        $model = $query->getModel();
        $term = trim($term);

        if ($term !== '') {
            $like = '%'.addcslashes($term, '%_\\').'%';
            $query->where(function (Builder $where) use ($like, $term, $model): void {
                foreach ($this->searchColumns ?: [$this->labelColumn] as $column) {
                    $where->orWhere($column, 'like', $like);
                }
                if (ctype_digit($term)) {
                    $where->orWhere($model->getQualifiedKeyName(), (int) $term);
                }
            });
        }

        if ($query->getQuery()->orders === null || $query->getQuery()->orders === []) {
            $query->orderBy($this->labelColumn);
        }

        return array_map(fn (Model $record) => $this->option($record), $query->limit($this->limit)->get()->all());
    }

    /**
     * Keys only: a list of them with ->multiple(), one or null otherwise.
     */
    public function sanitize(mixed $value, mixed $current = null): int|string|array|null
    {
        $keys = array_values(array_unique(array_filter(
            is_array($value) ? $value : [$value],
            static fn ($key) => (is_int($key) || is_string($key)) && $key !== '',
        )));

        return $this->multiple ? $keys : ($keys[0] ?? null);
    }

    public function implicitRules(Get $get): array
    {
        return [function (string $attribute, mixed $value, Closure $fail) use ($get): void {
            $keys = array_values(array_filter(is_array($value) ? $value : [$value], static fn ($key) => $key !== null && $key !== ''));
            if ($keys !== [] && $this->baseQuery($get)->whereKey($keys)->count() !== count(array_unique($keys))) {
                $fail('validation.exists')->translate();
            }
        }];
    }

    protected function fieldProperties(SchemaContext $context): array
    {
        $keys = array_values(array_filter((array) data_get($context->state, $this->path()), static fn ($key) => $key !== null && $key !== ''));
        $selected = $keys === [] ? [] : $this->baseQuery($context->get())->whereKey($keys)->get()
            ->sortBy(static fn (Model $record) => array_search($record->getKey(), $keys))
            ->map(fn (Model $record) => $this->option($record))->values()->all();

        return [
            'multiple' => $this->multiple,
            'selected' => $selected,
        ];
    }

    /**
     * @return Builder<Model>
     */
    private function baseQuery(Get $get): Builder
    {
        if ($this->query !== null) {
            return ($this->query)($get);
        }

        if ($this->model !== null) {
            return $this->model::query();
        }

        throw new VandaException("Relation [{$this->path()}] does not know its records. Add ->model(Client::class, 'name') or ->query(fn () => Client::query()).");
    }

    /**
     * @return array{value: int|string, label: string}
     */
    private function option(Model $record): array
    {
        return [
            'value' => $record->getKey(),
            'label' => (string) ($this->display !== null ? ($this->display)($record) : data_get($record, $this->labelColumn)),
        ];
    }
}
