<?php

declare(strict_types=1);

namespace Vanda\Fields;

use Vanda\Schema\SchemaContext;

/**
 * Password input with a show/hide button. Bind it to a Form property marked
 * #[Secret] so the value never travels back to the browser.
 */
final class Password extends Field
{
    private bool $strength = false;

    public function type(): string
    {
        return 'password';
    }

    /** Show a strength meter under the field (for new passwords). */
    public function strength(bool $strength = true): static
    {
        $this->strength = $strength;

        return $this;
    }

    protected function fieldProperties(SchemaContext $context): array
    {
        return ['strength' => $this->strength];
    }
}
