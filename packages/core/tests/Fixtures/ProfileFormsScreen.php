<?php

declare(strict_types=1);

namespace Vanda\Tests\Fixtures;

use Vanda\Actions\Button;
use Vanda\Attributes\Action;
use Vanda\Fields\Input;
use Vanda\Layouts\Layout;
use Vanda\Screen\Result;
use Vanda\Screen\Screen;

final class ProfileFormsScreen extends Screen
{
    /** @var array<string, mixed> */
    public array $profile = ['name' => ''];

    /** @var array<string, mixed> */
    public array $password = ['current' => ''];

    public function layout(): iterable
    {
        return [
            Layout::section('Profile')->schema([Input::make('profile.name')->required()])
                ->commands([Button::make('Save profile')->action($this->saveProfile(...))]),
            Layout::card('Password')->schema([Input::make('password.current')->required()])
                ->commands([Button::make('Change password')->action($this->savePassword(...))]),
        ];
    }

    #[Action]
    public function saveProfile(): Result
    {
        return Result::toast('Profile saved');
    }

    #[Action]
    public function savePassword(): Result
    {
        return Result::toast('Password changed');
    }
}
