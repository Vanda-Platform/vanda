<?php

declare(strict_types=1);

namespace Vanda\Tests\Fixtures;

use Vanda\Actions\Button;
use Vanda\Attributes\Action;
use Vanda\Attributes\Can;
use Vanda\Layouts\Text;
use Vanda\Screen\Result;
use Vanda\Screen\Screen;

#[Can('platform.users')]
final class SecuredScreen extends Screen
{
    public function title(): string
    {
        return 'Secured';
    }

    public function commandBar(): iterable
    {
        return [
            Button::make('Delete all')->danger()->action($this->purge(...)),
            Button::make('Refresh')->action($this->ping(...)),
        ];
    }

    public function layout(): iterable
    {
        return [Text::make('Only for users with platform.users')];
    }

    #[Action(validate: false)]
    #[Can('platform.users.delete')]
    public function purge(): Result
    {
        return Result::toast('Purged');
    }

    #[Action(validate: false)]
    public function ping(): Result
    {
        return Result::toast('Pong');
    }
}
