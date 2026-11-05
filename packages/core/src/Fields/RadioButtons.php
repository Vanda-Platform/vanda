<?php

declare(strict_types=1);

namespace Vanda\Fields;

use Vanda\Fields\Concerns\HasOptions;
use Vanda\Schema\SchemaContext;

/**
 * A segmented control: one of 2–5 short options, e.g. Draft | Review | Published.
 */
final class RadioButtons extends Field
{
    use HasOptions;

    public function type(): string
    {
        return 'segmented';
    }

    protected function fieldProperties(SchemaContext $context): array
    {
        return ['options' => $this->resolveOptions($context->get())];
    }
}
