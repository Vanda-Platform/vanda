<?php

declare(strict_types=1);

namespace Vanda\Fields;

use Vanda\Schema\SchemaContext;

/**
 * A colour as "#RRGGBB", with a picker and optional preset swatches.
 */
final class Color extends Field
{
    /** @var list<string> */
    private array $presets = [];

    public function type(): string
    {
        return 'color';
    }

    /**
     * @param list<string> $colors "#RRGGBB" values
     */
    public function presets(array $colors): static
    {
        $this->presets = array_values($colors);

        return $this;
    }

    protected function fieldProperties(SchemaContext $context): array
    {
        return ['presets' => $this->presets];
    }
}
