<?php

namespace App\Vanda\Screens;

use App\Enums\MailEncryption;
use App\Vanda\Settings\MailSettings;
use Vanda\Attributes\Can;
use Vanda\Fields\Input;
use Vanda\Fields\InputType;
use Vanda\Fields\Password;
use Vanda\Fields\RadioButtons;
use Vanda\Layouts\Layout;
use Vanda\Settings\SettingsScreen;

#[Can('platform.settings.mail')]
final class MailSettingsScreen extends SettingsScreen
{
    public MailSettings $mail;

    public function title(): string
    {
        return 'Почта';
    }

    public function description(): string
    {
        return 'Отправитель писем и SMTP-сервер';
    }

    public function layout(): iterable
    {
        return [
            Layout::section('Отправитель', 'Так подписаны письма сайта и панели.')->schema([
                Layout::grid([
                    Input::make('mail.fromName')->label('Имя')->required(),
                    Input::make('mail.fromAddress')->label('Адрес')->inputType(InputType::Email)->required(),
                ], columns: 2),
            ]),
            Layout::section('SMTP-сервер', 'Без сервера письма пишутся в журнал приложения.')->schema([
                Layout::grid([
                    Input::make('mail.host')->label('Сервер')->placeholder('smtp.example.com'),
                    Input::make('mail.port')->label('Порт')->inputType(InputType::Number),
                ], columns: 2),
                RadioButtons::make('mail.encryption')->label('Шифрование')->options(MailEncryption::class),
                Layout::grid([
                    Input::make('mail.username')->label('Логин'),
                    Password::make('mail.password')->label('Пароль')->help('Сохранённый пароль не показывается. Оставьте поле пустым, чтобы его не менять.'),
                ], columns: 2),
            ]),
        ];
    }
}
