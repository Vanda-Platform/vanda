<?php

declare(strict_types=1);

namespace Vanda\Fields\Concerns;

use BackedEnum;
use Closure;
use Illuminate\Contracts\Support\Arrayable;
use Vanda\Fields\Option;
use Vanda\Schema\Get;

/**
 * Options as [value => label], a list of Option::make($value, $label) (with a description under the
 * label or a hint beside it), a list of ['value' => …, 'label' => …, 'description' => …, 'hint' => …],
 * a BackedEnum class (labels from its label() method, descriptions from description() when it has one)
 * or a closure receiving the state.
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
     * @return list<array{value: int|string, label: string, hint?: string, description?: string}>
     */
    public function resolveOptions(Get $get): array
    {
        $options = $this->options instanceof Closure ? ($this->options)($get) : $this->options;

        if (is_string($options) && enum_exists($options)) {
            return array_map(
                static fn (BackedEnum $case) => Option::make(
                    $case->value,
                    method_exists($case, 'label') ? (string) $case->label() : $case->name,
                )->description(method_exists($case, 'description') ? $case->description() : null)->toArray(),
                $options::cases(),
            );
        }

        // A collection of Option objects stays as it is: toArray() would turn them into arrays of their own.
        if ($options instanceof Arrayable && ! $options instanceof \Traversable) {
            $options = $options->toArray();
        }

        $resolved = [];
        foreach ($options as $value => $label) {
            $resolved[] = match (true) {
                $label instanceof Option => $label->toArray(),
                is_array($label) && array_key_exists('value', $label) => array_filter([
                    'value' => $label['value'],
                    'label' => (string) ($label['label'] ?? $label['value']),
                    'hint' => isset($label['hint']) && $label['hint'] !== '' ? (string) $label['hint'] : null,
                    'description' => isset($label['description']) && $label['description'] !== '' ? (string) $label['description'] : null,
                ], static fn ($item, string $key) => $key === 'value' || $item !== null, ARRAY_FILTER_USE_BOTH),
                default => ['value' => $value, 'label' => (string) $label],
            };
        }

        return $resolved;
    }
}
