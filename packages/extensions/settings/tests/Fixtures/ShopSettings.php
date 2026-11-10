<?php

declare(strict_types=1);

namespace Vanda\Settings\Tests\Fixtures;

use Vanda\Attributes\Validate;
use Vanda\Settings\Settings;

final class ShopSettings extends Settings
{
    #[Validate('required|max:120')]
    public string $name = 'Vanda Shop';

    public bool $maintenance = false;

    public int $perPage = 20;

    public ShopMode $mode = ShopMode::Open;

    /** @var list<string> */
    public array $phones = [];
}
