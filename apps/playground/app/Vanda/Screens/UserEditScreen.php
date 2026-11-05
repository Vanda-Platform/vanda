<?php

namespace App\Vanda\Screens;

use App\Enums\Country;
use App\Enums\UserStatus;
use App\Models\User;
use App\Vanda\Forms\UserForm;
use Illuminate\Support\Str;
use Vanda\Actions\Button;
use Vanda\Actions\Link;
use Vanda\Actions\Variant;
use Vanda\Attributes\Action;
use Vanda\Attributes\Updated;
use Vanda\Fields\Input;
use Vanda\Fields\InputType;
use Vanda\Fields\Select;
use Vanda\Layouts\Layout;
use Vanda\Schema\Expr;
use Vanda\Schema\Get;
use Vanda\Screen\Result;
use Vanda\Screen\Screen;

final class UserEditScreen extends Screen
{
    public User $user;

    public UserForm $form;

    public function query(User $user): array
    {
        $this->user = $user;
        $this->form = UserForm::fromArray($user->only(['name', 'email', 'status', 'country', 'city', 'vat']));

        return [];
    }

    public function title(): string
    {
        return $this->user->exists ? $this->user->name : 'Новый пользователь';
    }

    public function description(): string
    {
        return $this->user->exists
            ? 'Создан '.$this->user->created_at?->translatedFormat('j F Y')
            : 'Заполните профиль, чтобы добавить пользователя';
    }

    public function commandBar(): iterable
    {
        return [
            Link::make('К списку')->icon('chevron-left')->variant(Variant::Ghost)->to(UserListScreen::class),
            Button::make('Удалить')
                ->icon('trash')
                ->danger()
                ->confirm('Учётная запись будет удалена без возможности восстановления.', 'Удалить пользователя?', 'Удалить')
                ->action($this->delete(...))
                ->visible($this->user->exists),
            Button::make('Сохранить')->icon('save')->primary()->hotkey('mod+s')->action($this->save(...)),
        ];
    }

    public function layout(): iterable
    {
        $inEu = Expr::field('form.country')->in(['DE', 'FR']);

        return [
            Layout::section('Профиль', 'Основные данные. Email используется для входа и уведомлений.')->schema([
                Layout::grid([
                    Input::make('form.name')->label('Имя')->required(),
                    Input::make('form.email')
                        ->inputType(InputType::Email)
                        ->label('Email')
                        ->required()
                        ->liveValidation()
                        ->help('Проверяется на уникальность, пока вы печатаете'),
                ], columns: 2),
                Select::make('form.status')->label('Статус')->options(UserStatus::class),
            ]),

            Layout::section('Адрес', 'Город зависит от страны, VAT ID нужен только компаниям из ЕС.')->schema([
                Layout::grid([
                    Select::make('form.country')->label('Страна')->options(Country::class)->placeholder('Не выбрана'),
                    Select::make('form.city')
                        ->label('Город')
                        ->placeholder('Сначала выберите страну')
                        ->dependsOn('form.country')
                        ->options(fn (Get $get) => Country::tryFrom((string) $get('form.country'))?->cities() ?? [])
                        ->help('Список приходит с сервера при смене страны'),
                ], columns: 2),
                Input::make('form.vat')
                    ->label('VAT ID')
                    ->placeholder('DE 123 456 789')
                    ->visibleWhen($inEu)
                    ->requiredWhen($inEu)
                    ->help('Поле появляется сразу, без запроса к серверу'),
            ]),
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
        $data = $this->form->only('name', 'email', 'status', 'country', 'city', 'vat');

        if (! $this->form->country?->inEu()) {
            $data['vat'] = null;
        }
        if (! $this->user->exists) {
            $data['password'] = Str::password();
        }

        $this->user->fill($data)->save();

        return Result::toast('Пользователь сохранён')->redirect(UserListScreen::class);
    }

    #[Action(validate: false)]
    public function delete(): Result
    {
        $this->user->delete();

        return Result::toast('Пользователь удалён')->redirect(UserListScreen::class);
    }
}
