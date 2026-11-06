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

    private ?string $mask = null;

    /** @var list<string> */
    private array $suggestions = [];

    private int|float|null $min = null;

    private int|float|null $max = null;

    private int|float|null $step = null;

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

    /**
     * Input mask: 9 is a digit, a a letter, * any character, the rest is typed for the user.
     * The value keeps the mask's characters: "+7 (999) 999-99-99" gives "+7 (912) 345-67-89".
     */
    public function mask(string $mask): static
    {
        $this->mask = $mask;

        return $this;
    }

    /**
     * Values offered while typing; any other value is still allowed (HTML datalist).
     *
     * @param list<string> $values
     */
    public function suggestions(array $values): static
    {
        $this->suggestions = array_values(array_map('strval', $values));

        return $this;
    }

    /**
     * Limits for InputType::Number (also the arrows' step).
     */
    public function min(int|float $min): static
    {
        $this->min = $min;

        return $this;
    }

    public function max(int|float $max): static
    {
        $this->max = $max;

        return $this;
    }

    public function step(int|float $step): static
    {
        $this->step = $step;

        return $this;
    }

    protected function fieldProperties(SchemaContext $context): array
    {
        return [
            'inputType' => $this->inputType === InputType::Text ? null : $this->inputType->value,
            'prefix' => $this->prefix,
            'suffix' => $this->suffix,
            'maxLength' => $this->maxLength,
            'mask' => $this->mask,
            'suggestions' => $this->suggestions === [] ? null : $this->suggestions,
            'min' => $this->min,
            'max' => $this->max,
            'step' => $this->step,
        ];
    }
}
