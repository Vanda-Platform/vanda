<?php

declare(strict_types=1);

namespace Vanda\Fields;

/**
 * On/off switch bound to a boolean value.
 */
final class Switcher extends Field
{
    public function type(): string
    {
        return 'switch';
    }
}
