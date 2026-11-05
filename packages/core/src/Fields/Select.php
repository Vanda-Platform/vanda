<?php

declare(strict_types=1);

namespace Vanda\Fields;

use Vanda\Fields\Concerns\HasOptions;
use Vanda\Schema\SchemaContext;

/**
 * Pick one value, or several with ->multiple():
 *
 *     Select::make('form.city')
 *         ->dependsOn('form.country')
 *         ->options(fn (Get $get) => City::where('country', $get('form.country'))->pluck('name', 'id'))
 */
final class Select extends Field
{
    use HasOptions;

    private bool $multiple = false;

    private bool $searchable = false;

    public function type(): string
    {
        return 'select';
    }

    /** The value becomes a list; chosen items are shown as removable tags. */
    public function multiple(bool $multiple = true): static
    {
        $this->multiple = $multiple;

        return $this;
    }

    /** Adds a search box to the list of options. */
    public function searchable(bool $searchable = true): static
    {
        $this->searchable = $searchable;

        return $this;
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
