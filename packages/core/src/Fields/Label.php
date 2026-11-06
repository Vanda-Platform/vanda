<?php

declare(strict_types=1);

namespace Vanda\Fields;

use Closure;
use Vanda\Schema\Get;
use Vanda\Schema\SchemaContext;

/**
 * A value shown among the fields but never edited or sent (Orchid's Label):
 *
 *     Label::make('form.number')->label('Order'),                       // the value at this path
 *     Label::make('created')->label('Created')->text(fn () => $this->order->created_at->diffForHumans()),
 */
final class Label extends Field
{
    private string|Closure|null $text = null;

    public function type(): string
    {
        return 'label';
    }

    /**
     * Text to show instead of the value at the path.
     *
     * @param string|Closure(Get): string $text
     */
    public function text(string|Closure $text): static
    {
        $this->text = $text;

        return $this;
    }

    public function isEditable(): bool
    {
        return false;
    }

    protected function fieldProperties(SchemaContext $context): array
    {
        return [
            'text' => $this->text instanceof Closure ? (string) ($this->text)($context->get()) : $this->text,
        ];
    }
}
