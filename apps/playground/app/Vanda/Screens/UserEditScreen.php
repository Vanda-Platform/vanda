<?php

namespace App\Vanda\Screens;

use App\Enums\Country;
use App\Enums\Timezone;
use App\Enums\UserStatus;
use App\Models\User;
use App\Vanda\Forms\UserForm;
use Illuminate\Support\Facades\Hash;
use Vanda\Access\Impersonation;
use Vanda\Access\Permissions;
use Vanda\Access\Role;
use Vanda\Actions\Button;
use Vanda\Actions\DropDown;
use Vanda\Actions\Link;
use Vanda\Attributes\Action;
use Vanda\Attributes\Can;
use Vanda\Attributes\Updated;
use Vanda\Fields\Input;
use Vanda\Fields\InputType;
use Vanda\Fields\Password;
use Vanda\Fields\PermissionsField;
use Vanda\Fields\Select;
use Vanda\Fields\Upload;
use Vanda\Layouts\ActionRow;
use Vanda\Layouts\Avatar;
use Vanda\Layouts\Badge;
use Vanda\Layouts\Layout;
use Vanda\Navigation\Crumb;
use Vanda\Schema\Expr;
use Vanda\Schema\Get;
use Vanda\Screen\Result;
use Vanda\Screen\Screen;

#[Can('platform.users')]
final class UserEditScreen extends Screen
{
    public User $user;

    public UserForm $form;

    public function query(User $user): array
    {
        $this->user = $user->load('roles');
        $this->form = UserForm::fromUser($this->user);

        return [];
    }

    public function title(): string
    {
        return $this->user->exists ? $this->user->name : 'Новый пользователь';
    }

    public function description(): ?string
    {
        if (! $this->user->exists) {
            return 'Учётная запись для входа в панель';
        }

        $login = $this->user->last_login_at ? 'последний вход '.$this->user->last_login_at->diffForHumans() : 'ещё не входил';

        return 'Создан '.$this->user->created_at?->translatedFormat('j F Y').' · '.$login;
    }

    public function avatar(): ?Avatar
    {
        return $this->user->exists
            ? Avatar::make($this->user->name)->image($this->user->avatarUrl())
            : null;
    }

    public function badge(): ?Badge
    {
        return $this->user->exists ? Badge::make($this->user->status->label(), $this->user->status->tone())->dot() : null;
    }

    public function breadcrumbs(): iterable
    {
        return [
            Crumb::make('Доступ'),
            Crumb::make('Пользователи')->to(UserListScreen::class),
            Crumb::make($this->title()),
        ];
    }

    public function commandBar(): iterable
    {
        $other = $this->user->exists && ! $this->user->is(auth()->user());

        return [
            Button::make('Войти как пользователь')->icon('log-in')->ghost()->action($this->impersonate(...))->visible($other),
            DropDown::make()->icon('more')->list([
                Link::make('Написать письмо')->icon('mail')->href('mailto:'.$this->user->email),
                Button::make('Удалить')->icon('trash')->danger()
                    ->confirm('Учётная запись будет удалена без возможности восстановления.', 'Удалить пользователя?', 'Удалить')
                    ->action($this->delete(...)),
            ])->visible($other),
            Button::make('Сохранить')->primary()->hotkey('mod+s')->action($this->save(...)),
        ];
    }

    public function layout(): iterable
    {
        $inEu = Expr::field('form.country')->in(['DE', 'FR']);

        return [
            Layout::section('Профиль', 'Основные данные и контакты. Email используется для входа и уведомлений.')->schema([
                Upload::make('form.avatar')->avatar($this->user->name ?? 'Новый пользователь')->maxSize(2)->group('avatar')
                    ->help('PNG или JPG, до 2 МБ'),
                Layout::grid([
                    Input::make('form.name')->label('Имя и фамилия')->required(),
                    Input::make('form.email')
                        ->inputType(InputType::Email)
                        ->label('Email')
                        ->required()
                        ->liveValidation(),
                    Input::make('form.phone')->label('Телефон')->prefix('+7')->inputType(InputType::Tel)->placeholder('(912) 345-67-89'),
                    Select::make('form.timezone')->label('Часовой пояс')->options(Timezone::options())->placeholder('Как у сервера'),
                    Select::make('form.country')->label('Страна')->options(Country::class)->placeholder('Не выбрана'),
                    Select::make('form.city')
                        ->label('Город')
                        ->placeholder('Сначала выберите страну')
                        ->dependsOn('form.country')
                        ->options(fn (Get $get) => Country::tryFrom((string) $get('form.country'))?->cities() ?? [])
                        ->help('Список обновляется при смене страны'),
                    Input::make('form.vat')
                        ->label('VAT ID')
                        ->placeholder('DE 123 456 789')
                        ->visibleWhen($inEu)
                        ->requiredWhen($inEu)
                        ->help('Обязателен для стран ЕС'),
                ], columns: 2),
            ]),
            Layout::section('Роли и доступ', 'Роль задаёт набор прав. Отдельные права можно разрешить или запретить лично этому пользователю.')->schema([
                Select::make('form.roles')->label('Роли')->multiple()->placeholder('Добавить роль…')
                    ->options(fn () => Role::query()->orderBy('name')->pluck('name', 'id')),
                PermissionsField::make('form.permissions')->label('Права доступа')->inherited($this->user->permissionSources()),
            ]),
            Layout::section('Пароль', $this->user->exists ? 'Оставьте поля пустыми, чтобы не менять пароль.' : 'Задайте пароль для входа.')->schema([
                Layout::grid([
                    Password::make('form.password')->label('Новый пароль')->strength(),
                    Password::make('form.password_confirmation')->label('Повторите пароль'),
                ], columns: 2),
            ]),
            Layout::section('Опасная зона', 'Эти действия влияют на доступ пользователя сразу.')->schema([
                Layout::actions([
                    ActionRow::make('Заблокировать доступ')
                        ->description('Пользователь не сможет войти в панель. Данные сохранятся.')
                        ->buttons([Button::make('Заблокировать')->action($this->block(...))])
                        ->visible($this->user->status !== UserStatus::Blocked),
                    ActionRow::make('Разблокировать доступ')
                        ->description('Пользователь снова сможет входить в панель.')
                        ->buttons([Button::make('Разблокировать')->action($this->unblock(...))])
                        ->visible($this->user->status === UserStatus::Blocked),
                    ActionRow::make('Удалить пользователя')
                        ->description('Учётная запись и её файлы будут удалены без возможности восстановления.')
                        ->buttons([
                            Button::make('Удалить')->icon('trash')->danger()
                                ->confirm('Учётная запись будет удалена без возможности восстановления.', 'Удалить пользователя?', 'Удалить')
                                ->action($this->delete(...)),
                        ]),
                ]),
            ])->visible($this->user->exists && ! $this->user->is(auth()->user())),
        ];
    }

    #[Updated('form.country')]
    public function resetCity(): void
    {
        $this->form->city = null;
    }

    #[Action]
    public function save(): Result
    {
        $created = ! $this->user->exists;
        $data = $this->form->only('name', 'email', 'phone', 'country', 'city', 'vat', 'timezone');
        $data['permissions'] = Permissions::clean($this->form->permissions);

        if (! $this->form->country?->inEu()) {
            $data['vat'] = null;
        }
        if ($this->form->password !== '') {
            $data['password'] = Hash::make($this->form->password);
        }

        $this->user->fill($data)->save();
        $this->user->replaceRoles($this->form->roles);
        $this->user->syncAttachments($this->form->avatar, 'avatar');

        // Saving keeps the user on the record; a new record opens at its own address.
        return $created ? Result::toast('Пользователь добавлен')->redirect(self::class, ['user' => $this->user]) : Result::toast('Пользователь сохранён');
    }

    #[Action(validate: false)]
    #[Can('platform.impersonate')]
    public function impersonate(): Result
    {
        Impersonation::loginAs($this->user);

        return Result::toast("Вы вошли как {$this->user->name}")->redirect(DashboardScreen::class);
    }

    #[Action(validate: false)]
    public function block(): Result
    {
        $this->user->update(['status' => UserStatus::Blocked]);

        return Result::toast("Доступ заблокирован: {$this->user->name}");
    }

    #[Action(validate: false)]
    public function unblock(): Result
    {
        $this->user->update(['status' => UserStatus::Active]);

        return Result::toast("Доступ восстановлен: {$this->user->name}");
    }

    #[Action(validate: false)]
    public function delete(): Result
    {
        $this->user->delete();

        return Result::toast('Пользователь удалён')->redirect(UserListScreen::class);
    }
}
