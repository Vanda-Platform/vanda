<?php

declare(strict_types=1);

namespace Vanda\Fields;

use Vanda\Schema\SchemaContext;

/**
 * Two numbers, "from" and "to". The value is ['from' => …, 'to' => …].
 */
final class NumberRange extends Field
{
    private ?string $suffix = null;

    public function type(): string
    {
        return 'numberrange';
    }

    public function suffix(string $suffix): static
    {
        $this->suffix = $suffix;

        return $this;
    }

    protected function fieldProperties(SchemaContext $context): array
    {
        return ['suffix' => $this->suffix];
    }
}
