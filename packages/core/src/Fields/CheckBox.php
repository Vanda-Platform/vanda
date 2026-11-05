<?php

declare(strict_types=1);

namespace Vanda\Fields;

/**
 * A single yes/no checkbox with its label on the right.
 */
final class CheckBox extends Field
{
    public function type(): string
    {
        return 'checkbox';
    }
}
