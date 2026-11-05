<?php

declare(strict_types=1);

namespace Vanda\Fields;

use Vanda\Schema\SchemaContext;

final class TextArea extends Field
{
    private int $rows = 4;

    private ?int $maxLength = null;

    public function type(): string
    {
        return 'textarea';
    }

    public function rows(int $rows): static
    {
        $this->rows = $rows;

        return $this;
    }

    /** Also shows a "12 / 500" counter under the field. */
    public function maxLength(int $length): static
    {
        $this->maxLength = $length;

        return $this;
    }

    protected function fieldProperties(SchemaContext $context): array
    {
        return ['rows' => $this->rows, 'maxLength' => $this->maxLength];
    }
}
