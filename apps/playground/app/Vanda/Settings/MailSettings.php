<?php

namespace App\Vanda\Settings;

use App\Enums\MailEncryption;
use Vanda\Attributes\Secret;
use Vanda\Attributes\Validate;
use Vanda\Settings\Settings;

/**
 * Отправитель писем и SMTP-сервер; пароль хранится зашифрованным и не уходит в браузер.
 */
final class MailSettings extends Settings
{
    #[Validate('required|max:120')]
    public string $fromName = 'Vanda';

    #[Validate('required|email')]
    public string $fromAddress = 'noreply@example.com';

    #[Validate('nullable|max:255')]
    public ?string $host = null;

    #[Validate('integer|between:1,65535')]
    public int $port = 587;

    public MailEncryption $encryption = MailEncryption::Tls;

    public ?string $username = null;

    #[Secret]
    public string $password = '';
}
