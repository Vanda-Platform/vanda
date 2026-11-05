<?php

declare(strict_types=1);

namespace Vanda\Attributes;

use Attribute;

/**
 * Laravel validation rules for a Form property: `#[Validate('required|email')]`.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class Validate
{
    /**
     * @param string|list<mixed> $rules
     */
    public function __construct(
        public string|array $rules,
    ) {}
}
