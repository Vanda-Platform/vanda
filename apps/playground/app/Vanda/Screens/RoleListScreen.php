<?php

namespace App\Vanda\Screens;

use Vanda\Access\Role;
use Vanda\Actions\Link;
use Vanda\Attributes\Can;
use Vanda\Layouts\Badge;
use Vanda\Layouts\Layout;
use Vanda\Layouts\TD;
use Vanda\Layouts\Tone;
use Vanda\Screen\Screen;
use Vanda\Panel;

#[Can('platform.roles')]
final class RoleListScreen extends Screen
{
    public function title(): string
    {
        return 'Роли';
    }

    public function description(): string
    {
        return 'Наборы прав, которые выдаются пользователям';
    }

    public function commandBar(): iterable
    {
        return [Link::make('Создать роль')->icon('plus')->primary()->to(RoleEditScreen::class)];
    }

    public function layout(): iterable
    {
        return [
            Layout::table()
                ->query(fn () => Role::query()->withCount('users'))
                ->defaultSort('name')
                ->rowLink(fn (Role $role) => Panel::url(RoleEditScreen::class, ['role' => $role]))
                ->columns([
                    TD::make('name', 'Название')->sortable(),
                    TD::make('slug', 'Код')->muted(),
                    TD::make('permissions', 'Права')->render(
                        fn (Role $role) => Badge::make(count(array_filter($role->permissions ?? [])).' прав', Tone::Accent),
                    ),
                    TD::make('users_count', 'Пользователей')->alignRight(),
                ]),
        ];
    }
}
