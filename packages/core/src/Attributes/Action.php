<?php

declare(strict_types=1);

namespace Vanda\Attributes;

use Attribute;

/**
 * Marks a public screen method as callable from the UI.
 *
 * Only methods with this attribute can be invoked by buttons; every other
 * public method stays an ordinary PHP method and is never exposed over HTTP.
 */
#[Attribute(Attribute::TARGET_METHOD)]
final class Action
{
    public function __construct(
        /** Validate the rules of every visible field before the method runs. */
        public bool $validate = true,
    ) {}
}
