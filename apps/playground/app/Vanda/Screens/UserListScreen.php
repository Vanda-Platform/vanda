<?php

namespace App\Vanda\Screens;

use App\Models\User;
use Vanda\Actions\Button;
use Vanda\Actions\Link;
use Vanda\Attributes\Action;
use Vanda\Layouts\Badge;
use Vanda\Layouts\Layout;
use Vanda\Layouts\TD;
use Vanda\Screen\Result;
use Vanda\Screen\Screen;
use Vanda\Vanda;

final class UserListScreen extends Screen
{
    public function title(): string
    {
        return 'Пользователи';
    }

    public function description(): string
    {
        return 'Учётные записи и доступ к панели';
    }

    public function commandBar(): iterable
    {
        return [
            Link::make('Создать пользователя')->icon('plus')->primary()->to(UserEditScreen::class),
        ];
    }

    public function layout(): iterable
    {
        return [
            Layout::table()
                ->rows(fn () => User::query()->latest('id')->paginate(10))
                ->rowLink(fn (User $user) => Vanda::url(UserEditScreen::class, ['user' => $user]))
                ->emptyText('Пользователей пока нет')
                ->columns([
                    TD::make('name', 'Пользователь'),
                    TD::make('email', 'Email')->muted(),
                    TD::make('country', 'Страна')->render(fn (User $user) => $user->country?->label() ?? '—'),
                    TD::make('status', 'Статус')->render(
                        fn (User $user) => Badge::make($user->status->label(), $user->status->tone())->dot(),
                    ),
                    TD::make('created_at', 'Создан')->muted()->render(fn (User $user) => $user->created_at?->translatedFormat('j M Y')),
                    TD::make('actions', '')->alignRight()->render(
                        fn (User $user) => Button::make('Удалить')
                            ->icon('trash')
                            ->ghost()
                            ->confirm("{$user->name} будет удалён без возможности восстановления.", 'Удалить пользователя?', 'Удалить', danger: true)
                            ->action($this->delete(...), ['user' => $user]),
                    ),
                ]),
        ];
    }

    #[Action(validate: false)]
    public function delete(User $user): Result
    {
        $user->delete();

        return Result::toast('Пользователь удалён');
    }
}
