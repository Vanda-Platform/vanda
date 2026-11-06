<?php

declare(strict_types=1);

namespace Vanda\Schema;

/**
 * A container whose buttons validate only the fields inside it: a modal (any of its buttons),
 * a section or a card (the buttons given to ->commands()).
 */
interface ValidationScope
{
    /**
     * Whether a press of this button validates only this container's fields.
     */
    public function scopes(Node $button): bool;
}
