<?php

declare(strict_types=1);

namespace Vanda\Fields;

use Vanda\Schema\SchemaContext;

/**
 * A list of rows with the same fields. Child paths are relative to a row:
 *
 *     Repeater::make('form.specs')
 *         ->fields([Input::make('name')->label('Name')->rules('required'), Input::make('value')->label('Value')])
 *         ->addLabel('Add a spec')
 *
 * The value is a list: [['name' => 'Weight', 'value' => '1 kg'], ...]. Child rules
 * are validated for every row ("form.specs.*.name"), and every child cleans its own value
 * (RichText, Upload, Select), so an Upload works inside a row too.
 */
final class Repeater extends Field
{
    /** @var list<Field> */
    private array $fields = [];

    private string $addLabel = 'Add';

    private ?int $max = null;

    private bool $table = false;

    public function type(): string
    {
        return 'repeater';
    }

    /**
     * @param list<Field> $fields fields with paths relative to the row
     */
    public function fields(array $fields): static
    {
        $this->fields = array_values($fields);

        return $this;
    }

    /**
     * @return list<Field>
     */
    public function getFields(): array
    {
        return $this->fields;
    }

    public function addLabel(string $label): static
    {
        $this->addLabel = $label;

        return $this;
    }

    public function max(int $rows): static
    {
        $this->max = $rows;

        return $this;
    }

    /**
     * Rows as a table with the labels as column headers (see Matrix), for short values.
     * A field without a label keeps its path as the header.
     */
    public function table(bool $table = true): static
    {
        $this->table = $table;

        return $this;
    }

    /**
     * Keeps only the child paths of every row; anything else from the browser is dropped.
     *
     * @return list<array<string, mixed>>
     */
    public function sanitize(mixed $value, mixed $current = null): array
    {
        $before = is_array($current) ? array_values($current) : [];
        $rows = [];

        foreach (is_array($value) ? array_values($value) : [] as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            $clean = [];
            foreach ($this->fields as $field) {
                $item = data_get($row, $field->path());
                if ($field->isEditable() && $item !== null) {
                    data_set($clean, $field->path(), $field->sanitize($item, $this->previous($field, $before, $index)));
                }
            }
            $rows[] = $clean;
        }

        return $this->max === null ? $rows : array_slice($rows, 0, $this->max);
    }

    protected function fieldProperties(SchemaContext $context): array
    {
        $rows = array_values((array) data_get($context->state, $this->path()));

        return [
            'fields' => array_map(function (Field $field) use ($context, $rows): array {
                $schema = $field->toSchema($context);
                if ($field instanceof Upload) {
                    // One template serves every row, so it describes the files of all of them.
                    $schema['files'] = $field->describe($this->everyRow($field, $rows));
                }

                return $schema;
            }, $this->fields),
            'addLabel' => $this->addLabel,
            'max' => $this->max,
            'table' => $this->table,
        ];
    }

    /**
     * A child's value before this request: the same row's, or for an Upload the files of
     * every row, so a file stays when its row is moved.
     *
     * @param list<mixed> $rows
     */
    private function previous(Field $field, array $rows, int $index): mixed
    {
        return $field instanceof Upload ? $this->everyRow($field, $rows) : data_get($rows[$index] ?? [], $field->path());
    }

    /**
     * @param list<mixed> $rows
     * @return list<mixed>
     */
    private function everyRow(Field $field, array $rows): array
    {
        return array_merge(...array_map(static fn ($row) => (array) data_get($row, $field->path()), $rows));
    }
}
