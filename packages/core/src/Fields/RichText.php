<?php

declare(strict_types=1);

namespace Vanda\Fields;

use Vanda\Schema\SchemaContext;
use Vanda\Support\Html;

/**
 * Formatted text (Tiptap): headings, bold, italic, lists, quotes, code and links.
 * The value is HTML, cleaned on the server to the tags the editor produces:
 *
 *     RichText::make('form.content')->label('Text')->placeholder('Write something…'),
 */
final class RichText extends Field
{
    private int $minHeight = 200;

    public function type(): string
    {
        return 'richtext';
    }

    /**
     * Height of the empty editor in pixels; it grows with the text.
     */
    public function minHeight(int $pixels): static
    {
        $this->minHeight = $pixels;

        return $this;
    }

    public function sanitize(mixed $value, mixed $current = null): string
    {
        return is_string($value) ? Html::clean($value) : '';
    }

    protected function fieldProperties(SchemaContext $context): array
    {
        return ['minHeight' => $this->minHeight];
    }
}
