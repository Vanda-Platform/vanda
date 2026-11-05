<?php

declare(strict_types=1);

namespace Vanda\Tests\Fixtures;

use Vanda\Actions\Button;
use Vanda\Actions\Link;
use Vanda\Attributes\Action;
use Vanda\Layouts\Layout;
use Vanda\Layouts\TD;
use Vanda\Screen\Result;
use Vanda\Screen\Screen;

final class UserListScreen extends Screen
{
    public function title(): string
    {
        return 'Users';
    }

    public function commandBar(): iterable
    {
        return [Link::make('Create user')->primary()->to(UserEditScreen::class)];
    }

    public function layout(): iterable
    {
        return [
            Layout::table()
                ->rows(fn () => User::query()->orderBy('id')->get())
                ->rowLink(fn (User $user) => route('users.edit', $user))
                ->columns([
                    TD::make('name', 'Name'),
                    TD::make('email', 'Email')->muted(),
                    TD::make('actions', '')->alignRight()->render(
                        fn (User $user) => Button::make('Delete')->danger()->action($this->delete(...), ['user' => $user]),
                    ),
                ]),
        ];
    }

    #[Action(validate: false)]
    public function delete(User $user): Result
    {
        $user->delete();

        return Result::toast('User deleted');
    }
}
