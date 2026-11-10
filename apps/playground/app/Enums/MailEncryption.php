<?php

namespace App\Enums;

enum MailEncryption: string
{
    case Tls = 'tls';
    case Ssl = 'ssl';
    case None = 'none';

    public function label(): string
    {
        return match ($this) {
            self::Tls => 'STARTTLS',
            self::Ssl => 'SSL',
            self::None => 'Без шифрования',
        };
    }
}
