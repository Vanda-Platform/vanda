<?php

namespace App\Vanda\Screens;

use App\Vanda\Settings\SiteSettings;
use Vanda\Attributes\Can;
use Vanda\Fields\Input;
use Vanda\Fields\InputType;
use Vanda\Fields\Switcher;
use Vanda\Fields\TextArea;
use Vanda\Layouts\Layout;
use Vanda\Schema\Expr;
use Vanda\Settings\SettingsScreen;

#[Can('platform.settings.site')]
final class SiteSettingsScreen extends SettingsScreen
{
    public SiteSettings $site;

    public function title(): string
    {
        return 'Сайт';
    }

    public function description(): string
    {
        return 'Название, контакты и режим обслуживания';
    }

    public function layout(): iterable
    {
        return [
            Layout::section('Сайт', 'Так сайт называется в заголовках, письмах и поиске.')->schema([
                Input::make('site.name')->label('Название')->required(),
                TextArea::make('site.tagline')->label('Подзаголовок')->maxLength(300),
            ]),
            Layout::section('Контакты', 'Куда писать и звонить посетителям.')->schema([
                Layout::grid([
                    Input::make('site.supportEmail')->label('Почта поддержки')->inputType(InputType::Email)->placeholder('help@example.com'),
                    Input::make('site.phone')->label('Телефон')->inputType(InputType::Tel)->placeholder('+7 900 000-00-00'),
                ], columns: 2),
            ]),
            Layout::section('Обслуживание', 'Пока режим включён, посетители видят сообщение вместо сайта. Панель работает.')->schema([
                Switcher::make('site.maintenance')->label('Режим обслуживания'),
                TextArea::make('site.maintenanceMessage')->label('Сообщение посетителям')->maxLength(500)
                    ->placeholder('Сайт обновляется и скоро вернётся')
                    ->visibleWhen(Expr::field('site.maintenance')->eq(true)),
            ]),
        ];
    }
}
