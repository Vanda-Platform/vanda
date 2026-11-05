<?php

declare(strict_types=1);

namespace Vanda\Schema;

use Vanda\Screen\Screen;

/**
 * What a node may look at while describing itself: the screen and its state.
 */
final class SchemaContext
{
    /**
     * @param array<string, mixed> $state
     */
    public function __construct(
        public readonly Screen $screen,
        public readonly array $state,
    ) {}

    public function get(): Get
    {
        return new Get($this->state);
    }
}
