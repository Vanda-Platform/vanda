<?php

declare(strict_types=1);

namespace Vanda\Fields;

use Vanda\Schema\SchemaContext;

/**
 * Editable pairs, e.g. UTM tags or HTTP headers.
 * The value is a list of ['key' => …, 'value' => …].
 */
final class KeyValue extends Field
{
    private string $keyLabel = 'Key';

    private string $valueLabel = 'Value';

    private string $addLabel = 'Add';

    public function type(): string
    {
        return 'keyvalue';
    }

    public function labels(string $key, string $value, string $add): static
    {
        $this->keyLabel = $key;
        $this->valueLabel = $value;
        $this->addLabel = $add;

        return $this;
    }

    /**
     * Keeps only well-formed rows from the browser.
     *
     * @return list<array{key: string, value: string}>
     */
    public function sanitize(mixed $value): array
    {
        $rows = [];

        foreach (is_array($value) ? $value : [] as $row) {
            if (is_array($row)) {
                $rows[] = ['key' => (string) ($row['key'] ?? ''), 'value' => (string) ($row['value'] ?? '')];
            }
        }

        return $rows;
    }

    protected function fieldProperties(SchemaContext $context): array
    {
        return ['keyLabel' => $this->keyLabel, 'valueLabel' => $this->valueLabel, 'addLabel' => $this->addLabel];
    }
}
