<?php

declare(strict_types=1);

namespace Vanda\Settings\Tests\Fixtures;

use Vanda\Attributes\Can;
use Vanda\Fields\Input;
use Vanda\Fields\InputType;
use Vanda\Fields\RadioButtons;
use Vanda\Fields\Switcher;
use Vanda\Layouts\Layout;
use Vanda\Settings\SettingsScreen;

#[Can('settings.shop')]
final class ShopSettingsScreen extends SettingsScreen
{
    public ShopSettings $shop;

    public function title(): string
    {
        return 'Shop';
    }

    public function description(): string
    {
        return 'Name and maintenance';
    }

    public function layout(): iterable
    {
        return [
            Layout::section('Shop')->schema([
                Input::make('shop.name')->label('Name'),
                Switcher::make('shop.maintenance')->label('Maintenance'),
                Input::make('shop.perPage')->label('Rows per page')->inputType(InputType::Number),
                RadioButtons::make('shop.mode')->label('Mode')->options(ShopMode::class),
            ]),
        ];
    }
}
