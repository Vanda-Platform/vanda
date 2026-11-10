<?php

declare(strict_types=1);

namespace Vanda\Settings\Tests\Fixtures;

use Vanda\Attributes\Can;
use Vanda\Fields\Input;
use Vanda\Fields\Password;
use Vanda\Layouts\Layout;
use Vanda\Settings\SettingsScreen;

#[Can('settings.mail')]
final class MailSettingsScreen extends SettingsScreen
{
    public MailSettings $mail;

    public function title(): string
    {
        return 'Mail';
    }

    public function description(): string
    {
        return 'Sender and SMTP';
    }

    public function layout(): iterable
    {
        return [
            Layout::section('Mail')->schema([
                Input::make('mail.from')->label('From'),
                Password::make('mail.smtpPassword')->label('SMTP password'),
            ]),
        ];
    }
}
