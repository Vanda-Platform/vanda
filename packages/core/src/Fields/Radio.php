<?php

declare(strict_types=1);

namespace Vanda\Fields;

use Vanda\Fields\Concerns\HasOptions;
use Vanda\Schema\SchemaContext;

/**
 * One of a few options shown at once as radio buttons.
 */
final class Radio extends Field
{
    use HasOptions;

    private bool $inline = false;

    public function type(): string
    {
        return 'radio';
    }

    /** Options in one row instead of a column. */
    public function inline(bool $inline = true): static
    {
        $this->inline = $inline;

        return $this;
    }

    protected function fieldProperties(SchemaContext $context): array
    {
        return ['options' => $this->resolveOptions($context->get()), 'inline' => $this->inline];
    }
}
