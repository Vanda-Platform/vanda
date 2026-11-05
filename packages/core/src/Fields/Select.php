<?php

declare(strict_types=1);

namespace Vanda\Fields;

use BackedEnum;
use Closure;
use Illuminate\Contracts\Support\Arrayable;
use Vanda\Schema\Get;
use Vanda\Schema\SchemaContext;

/**
 * Pick one value (or several with ->multiple()) from a list of options.
 *
 * Options may be an array [value => label], a list of ['value' => …, 'label' => …],
 * a BackedEnum class, or a closure receiving the current state:
 *
 *     Select::make('form.city')
 *         ->dependsOn('form.country')
 *         ->options(fn (Get $get) => City::where('country', $get('form.country'))->pluck('name', 'id'))
 */
final class Select extends Field
{
    /** @var iterable<mixed>|Closure|class-string<BackedEnum> */
    private iterable|Closure|string $options = [];

    private bool $multiple = false;

    private bool $searchable = false;

    public function type(): string
    {
        return 'select';
    }

    /**
     * @param iterable<mixed>|Closure(Get): iterable<mixed>|class-string<BackedEnum> $options
     */
    public function options(iterable|Closure|string $options): static
    {
        $this->options = $options;

        return $this;
    }

    public function multiple(bool $multiple = true): static
    {
        $this->multiple = $multiple;

        return $this;
    }

    public function searchable(bool $searchable = true): static
    {
        $this->searchable = $searchable;

        return $this;
    }

    /**
     * @return list<array{value: int|string, label: string}>
     */
    public function resolveOptions(Get $get): array
    {
        $options = $this->options;

        if ($options instanceof Closure) {
            $options = $options($get);
        }

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

    protected function fieldProperties(SchemaContext $context): array
    {
        return [
            'options' => $this->resolveOptions($context->get()),
            'multiple' => $this->multiple,
            'searchable' => $this->searchable,
        ];
    }
}
