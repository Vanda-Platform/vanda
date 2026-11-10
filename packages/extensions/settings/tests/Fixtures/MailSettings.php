<?php

declare(strict_types=1);

namespace Vanda\Settings\Tests\Fixtures;

use Vanda\Attributes\Secret;
use Vanda\Attributes\Validate;
use Vanda\Settings\Settings;

final class MailSettings extends Settings
{
    #[Validate('nullable|email')]
    public ?string $from = null;

    #[Secret]
    public string $smtpPassword = '';
}
