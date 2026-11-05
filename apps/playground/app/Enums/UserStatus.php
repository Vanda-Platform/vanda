<?php

namespace App\Enums;

use Vanda\Layouts\Tone;

enum UserStatus: string
{
    case Active = 'active';
    case Invited = 'invited';
    case Blocked = 'blocked';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Активен',
            self::Invited => 'Приглашён',
            self::Blocked => 'Заблокирован',
        };
    }

    public function tone(): Tone
    {
        return match ($this) {
            self::Active => Tone::Success,
            self::Invited => Tone::Warning,
            self::Blocked => Tone::Danger,
        };
    }
}
