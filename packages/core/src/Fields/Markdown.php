<?php

declare(strict_types=1);

namespace Vanda\Fields;

use Vanda\Schema\SchemaContext;

/**
 * Markdown with a "Text / Preview" switch. The value is the Markdown source;
 * the preview escapes raw HTML.
 *
 *     Markdown::make('form.notes')->label('Notes'),
 */
final class Markdown extends Field
{
    private int $minHeight = 160;

    public function type(): string
    {
        return 'markdown';
    }

    public function minHeight(int $pixels): static
    {
        $this->minHeight = $pixels;

        return $this;
    }

    public function sanitize(mixed $value, mixed $current = null): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    protected function fieldProperties(SchemaContext $context): array
    {
        return ['minHeight' => $this->minHeight];
    }
}
