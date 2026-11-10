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
 * How rows are drawn follows from the fields (see resolvedLayout()). In a List, Enter adds the next row
 * and Backspace in an empty one removes it. A Block's header names it by its first text field, folds it
 * (->collapsed() starts folded), and its «⋯» duplicates it, adds a row below or moves it. Rows move by
 * their grip too (mouse, finger, arrow keys); a removed filled row comes back with «Вернуть».
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

    private ?RepeaterLayout $layout = null;

    private ?string $itemLabel = null;

    private bool $collapsed = false;

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
     * How rows are drawn; by default chosen from the fields (see RepeaterLayout).
     *
     *     Repeater::make('form.slides')->layout(RepeaterLayout::Blocks)
     */
    public function layout(RepeaterLayout $layout): static
    {
        $this->layout = $layout;

        return $this;
    }

    /**
     * Rows as a table with the labels as column headers (see Matrix), for short values.
     * The same as ->layout(RepeaterLayout::Table).
     */
    public function table(bool $table = true): static
    {
        $this->layout = $table ? RepeaterLayout::Table : null;

        return $this;
    }

    /**
     * The name of one row in the Blocks layout: "Вариант" gives "Вариант 1", "Вариант 2".
     */
    public function itemLabel(string $label): static
    {
        $this->itemLabel = $label;

        return $this;
    }

    /**
     * Blocks open folded, each a line with its name and the value of its first text field
     * ("День 2 · Практика"), for long rows in long lists. A block with an error and a new one open
     * by themselves; the user folds and unfolds any of them, or all at once.
     *
     *     Repeater::make('form.program')->itemLabel('День')->collapsed()->fields([...])
     */
    public function collapsed(bool $collapsed = true): static
    {
        $this->collapsed = $collapsed;

        return $this;
    }

    /**
     * The layout in use: the one set, or one field → List, up to four short fields → Table,
     * otherwise Blocks.
     */
    public function resolvedLayout(): RepeaterLayout
    {
        if ($this->layout !== null) {
            return $this->layout;
        }

        $short = array_filter($this->fields, static fn (Field $field) => self::isShort($field));

        return match (true) {
            count($this->fields) === 1 && $short !== [] => RepeaterLayout::List,
            count($this->fields) <= 4 && count($short) === count($this->fields) => RepeaterLayout::Table,
            default => RepeaterLayout::Blocks,
        };
    }

    /**
     * A field that fits one line of a table or a list.
     */
    private static function isShort(Field $field): bool
    {
        return match (true) {
            $field instanceof Select, $field instanceof Relation => ! $field->isMultiple(),
            default => $field instanceof Input || $field instanceof DateTimer || $field instanceof Switcher
                || $field instanceof CheckBox || $field instanceof Color || $field instanceof Label,
        };
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
            'layout' => $this->resolvedLayout()->value,
            'itemLabel' => $this->itemLabel,
            'collapsed' => $this->collapsed,
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
