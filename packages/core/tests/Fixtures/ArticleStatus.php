<?php

declare(strict_types=1);

namespace Vanda\Tests\Fixtures;

enum ArticleStatus: string
{
    case Draft = 'draft';
    case Published = 'published';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Черновик',
            self::Published => 'Опубликована',
        };
    }
}
