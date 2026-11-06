<?php

namespace App\Vanda\Screens;

use App\Vanda\Forms\RoleForm;
use Vanda\Access\Permissions;
use Vanda\Access\Role;
use Vanda\Actions\Button;
use Vanda\Attributes\Action;
use Vanda\Attributes\Can;
use Vanda\Fields\Input;
use Vanda\Fields\PermissionsField;
use Vanda\Layouts\Layout;
use Vanda\Navigation\Crumb;
use Vanda\Schema\Expr;
use Vanda\Screen\Result;
use Vanda\Screen\Screen;

#[Can('platform.roles')]
final class RoleEditScreen extends Screen
{
    public Role $role;

    public RoleForm $form;

    public function query(Role $role): array
    {
        $this->role = $role;
        $this->form = RoleForm::fromArray($role->only(['name', 'slug']) + ['permissions' => $role->permissions ?? []]);

        return [];
    }

    public function title(): string
    {
        return $this->role->exists ? $this->role->name : 'Новая роль';
    }

    public function breadcrumbs(): iterable
    {
        return [Crumb::make('Доступ'), Crumb::make('Роли')->to(RoleListScreen::class), Crumb::make($this->title())];
    }

    public function commandBar(): iterable
    {
        return [
            Button::make('Удалить')->icon('trash')->danger()->confirm('Пользователи потеряют права этой роли.', 'Удалить роль?', 'Удалить')
                ->action($this->delete(...))->visible($this->role->exists),
            Button::make('Сохранить')->icon('save')->primary()->hotkey('mod+s')->action($this->save(...)),
        ];
    }

    public function layout(): iterable
    {
        $slug = Input::make('form.slug')->label('Код роли')->help('Латиница, используется в коде');

        return [
            Layout::section('Роль', 'Название видят администраторы, код используется в коде и API.')->schema([
                Layout::grid([
                    Input::make('form.name')->label('Название')->required(),
                    // A new role gets its code from the name right in the browser.
                    $this->role->exists ? $slug : $slug->computed(Expr::field('form.name')->slug()),
                ], columns: 2),
            ]),
            Layout::section('Права', 'Что могут делать пользователи с этой ролью.')->schema([
                PermissionsField::make('form.permissions'),
            ]),
        ];
    }

    #[Action]
    public function save(): Result
    {
        $created = ! $this->role->exists;
        $this->role->fill([
            'name' => $this->form->name,
            'slug' => $this->form->slug,
            'permissions' => Permissions::clean($this->form->permissions),
        ])->save();

        // Saving keeps the user on the record; a new record opens at its own address.
        return $created ? Result::toast('Роль добавлена')->redirect(self::class, ['role' => $this->role]) : Result::toast('Роль сохранена');
    }

    #[Action(validate: false)]
    public function delete(): Result
    {
        $this->role->users()->detach();
        $this->role->delete();

        return Result::toast('Роль удалена')->redirect(RoleListScreen::class);
    }
}
