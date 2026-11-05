<?php

declare(strict_types=1);

namespace Vanda\Tests\Fixtures;

use Vanda\Actions\Button;
use Vanda\Attributes\Action;
use Vanda\Attributes\Updated;
use Vanda\Fields\Input;
use Vanda\Fields\InputType;
use Vanda\Fields\Select;
use Vanda\Fields\Switcher;
use Vanda\Layouts\Layout;
use Vanda\Schema\Expr;
use Vanda\Schema\Get;
use Vanda\Screen\Result;
use Vanda\Screen\Screen;

final class UserEditScreen extends Screen
{
    public const COUNTRIES = ['RU' => 'Russia', 'DE' => 'Germany', 'FR' => 'France'];

    public const CITIES = [
        'RU' => [1 => 'Moscow', 2 => 'Kazan'],
        'DE' => [10 => 'Berlin', 11 => 'Munich'],
        'FR' => [20 => 'Paris'],
    ];

    public User $user;

    public UserForm $form;

    public function query(User $user): array
    {
        $this->user = $user;
        $this->form = UserForm::fromArray($user->only(['name', 'email', 'country', 'city', 'vat']));

        return ['countries' => count(self::COUNTRIES)];
    }

    public function title(): string
    {
        return $this->user->exists ? 'Edit user' : 'Create user';
    }

    public function commandBar(): iterable
    {
        return [
            Button::make('Delete')->danger()->confirm('The user will be removed.')->action($this->delete(...))->visible($this->user->exists),
            Button::make('Save')->primary()->hotkey('mod+s')->action($this->save(...)),
        ];
    }

    public function layout(): iterable
    {
        $inEu = Expr::field('form.country')->in(['DE', 'FR']);

        return [
            Layout::section('Profile', 'Contacts and address')->schema([
                Layout::grid([
                    Input::make('form.name')->label('Name'),
                    Input::make('form.email')->inputType(InputType::Email)->label('Email')->liveValidation(),
                    Select::make('form.country')->label('Country')->options(self::COUNTRIES),
                    Select::make('form.city')
                        ->label('City')
                        ->dependsOn('form.country')
                        ->options(fn (Get $get) => self::CITIES[$get('form.country')] ?? []),
                    Input::make('form.vat')->label('VAT ID')->visibleWhen($inEu)->requiredWhen($inEu),
                    Switcher::make('form.admin')->label('Administrator')->disabled(),
                ], columns: 2),
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
        $this->user->fill($this->form->only('name', 'email', 'country', 'city', 'vat'))->save();

        return Result::toast('User saved')->redirect(UserListScreen::class);
    }

    #[Action(validate: false)]
    public function delete(): Result
    {
        $this->user->delete();

        return Result::redirectTo(UserListScreen::class);
    }

    /** Public, but not an action: must never be reachable from the browser. */
    public function helper(): string
    {
        return 'internal';
    }
}
