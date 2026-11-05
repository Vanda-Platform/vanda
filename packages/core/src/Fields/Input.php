<?php

declare(strict_types=1);

namespace Vanda\Fields;

use Vanda\Schema\SchemaContext;

final class Input extends Field
{
    private InputType $inputType = InputType::Text;

    private ?string $prefix = null;

    private ?string $suffix = null;

    private ?int $maxLength = null;

    public function type(): string
    {
        return 'input';
    }

    public function inputType(InputType $type): static
    {
        $this->inputType = $type;

        return $this;
    }

    /** Text shown before the value, e.g. "https://" or "+7". */
    public function prefix(string $prefix): static
    {
        $this->prefix = $prefix;

        return $this;
    }

    /** Text shown after the value, e.g. "₽". */
    public function suffix(string $suffix): static
    {
        $this->suffix = $suffix;

        return $this;
    }

    public function maxLength(int $length): static
    {
        $this->maxLength = $length;

        return $this;
    }

    protected function fieldProperties(SchemaContext $context): array
    {
        return [
            'inputType' => $this->inputType === InputType::Text ? null : $this->inputType->value,
            'prefix' => $this->prefix,
            'suffix' => $this->suffix,
            'maxLength' => $this->maxLength,
        ];
    }
}
