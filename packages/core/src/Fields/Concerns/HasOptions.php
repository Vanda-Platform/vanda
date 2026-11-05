<?php

declare(strict_types=1);

namespace Vanda\Fields\Concerns;

use BackedEnum;
use Closure;
use Illuminate\Contracts\Support\Arrayable;
use Vanda\Schema\Get;

/**
 * Options as [value => label], a list of ['value' => …, 'label' => …], a BackedEnum
 * class (labels from its label() method) or a closure receiving the state.
 */
trait HasOptions
{
    /** @var iterable<mixed>|Closure|class-string<BackedEnum> */
    private iterable|Closure|string $options = [];

    /**
     * @param iterable<mixed>|Closure(Get): iterable<mixed>|class-string<BackedEnum> $options
     */
    public function options(iterable|Closure|string $options): static
    {
        $this->options = $options;

        return $this;
    }

    /**
     * @return list<array{value: int|string, label: string}>
     */
    public function resolveOptions(Get $get): array
    {
        $options = $this->options instanceof Closure ? ($this->options)($get) : $this->options;

        if (is_string($options) && enum_exists($options)) {
            return array_map(
                static fn (BackedEnum $case) => [
                    'value' => $case->value,
                    'label' => method_exists($case, 'label') ? $case->label() : $case->name,
                ],
                $options::cases(),
            );
        }

        if ($options instanceof Arrayable) {
            $options = $options->toArray();
        }

        $resolved = [];
        foreach ($options as $value => $label) {
            $resolved[] = is_array($label) && array_key_exists('value', $label)
                ? ['value' => $label['value'], 'label' => (string) ($label['label'] ?? $label['value'])]
                : ['value' => $value, 'label' => (string) $label];
        }

        return $resolved;
    }
}
