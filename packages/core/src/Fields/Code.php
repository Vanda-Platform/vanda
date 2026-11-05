<?php

declare(strict_types=1);

namespace Vanda\Fields;

use Vanda\Schema\SchemaContext;

/**
 * Code editor (CodeMirror 6) with highlighting; the language support loads only when used:
 *
 *     Code::make('form.settings')->language(CodeLanguage::Json)->rules('nullable|json'),
 */
final class Code extends Field
{
    private CodeLanguage $language = CodeLanguage::Plain;

    private int $minHeight = 160;

    private bool $lineNumbers = true;

    public function type(): string
    {
        return 'code';
    }

    public function language(CodeLanguage $language): static
    {
        $this->language = $language;

        return $this;
    }

    public function minHeight(int $pixels): static
    {
        $this->minHeight = $pixels;

        return $this;
    }

    public function lineNumbers(bool $show = true): static
    {
        $this->lineNumbers = $show;

        return $this;
    }

    public function sanitize(mixed $value, mixed $current = null): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    protected function fieldProperties(SchemaContext $context): array
    {
        return ['language' => $this->language->value, 'minHeight' => $this->minHeight, 'lineNumbers' => $this->lineNumbers];
    }
}
