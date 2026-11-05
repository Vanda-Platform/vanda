<?php

declare(strict_types=1);

namespace Vanda\Attributes;

use Attribute;

/**
 * Runs the method after the given state path was changed by the user.
 *
 * The path may end with a wildcard: `form.*` matches every field of `form`.
 * The method may accept `$value` (the new value) and `$path` (the exact path).
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class Updated
{
    public function __construct(
        public string $path,
    ) {}
}
