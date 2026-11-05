<?php

namespace App\Enums;

use Vanda\Layouts\Tone;

enum PageStatus: string
{
    case Published = 'published';
    case Draft = 'draft';
    case Hidden = 'hidden';

    public function label(): string
    {
        return match ($this) {
            self::Published => 'Опубликована',
            self::Draft => 'Черновик',
            self::Hidden => 'Скрыта из меню',
        };
    }

    public function tone(): Tone
    {
        return match ($this) {
            self::Published => Tone::Success,
            self::Draft, self::Hidden => Tone::Neutral,
        };
    }
}
