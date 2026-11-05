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
 * are validated for every row ("form.specs.*.name").
 */
final class Repeater extends Field
{
    /** @var list<Field> */
    private array $fields = [];

    private string $addLabel = 'Add';

    private ?int $max = null;

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
     * Keeps only the child paths of every row; anything else from the browser is dropped.
     *
     * @return list<array<string, mixed>>
     */
    public function sanitize(mixed $value): array
    {
        $rows = [];

        foreach (is_array($value) ? array_values($value) : [] as $row) {
            if (! is_array($row)) {
                continue;
            }

            $clean = [];
            foreach ($this->fields as $field) {
                if ($field->isEditable() && data_get($row, $field->path()) !== null) {
                    data_set($clean, $field->path(), data_get($row, $field->path()));
                }
            }
            $rows[] = $clean;
        }

        return $this->max === null ? $rows : array_slice($rows, 0, $this->max);
    }

    protected function fieldProperties(SchemaContext $context): array
    {
        return [
            'fields' => array_map(static fn (Field $field) => $field->toSchema($context), $this->fields),
            'addLabel' => $this->addLabel,
            'max' => $this->max,
        ];
    }
}
