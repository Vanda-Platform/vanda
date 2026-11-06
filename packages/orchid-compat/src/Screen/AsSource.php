<?php

declare(strict_types=1);

namespace Orchid\Screen;

/**
 * Orchid's AsSource: TD and Sight read values with getContent(). Vanda reads attributes
 * directly; the trait keeps models and their callers working.
 */
trait AsSource
{
    public function getContent(string $field): mixed
    {
        return data_get($this, $field);
    }
}
