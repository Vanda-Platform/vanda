<?php

namespace App\Vanda\Settings;

use Vanda\Attributes\Validate;
use Vanda\Settings\Settings;

/**
 * Как называется сайт и куда писать посетителям; читается где угодно: SiteSettings::get()->name.
 */
final class SiteSettings extends Settings
{
    #[Validate('required|max:120')]
    public string $name = 'Vanda Playground';

    #[Validate('nullable|max:300')]
    public ?string $tagline = null;

    #[Validate('nullable|email')]
    public ?string $supportEmail = null;

    public ?string $phone = null;

    public bool $maintenance = false;

    #[Validate('nullable|max:500')]
    public ?string $maintenanceMessage = null;
}
