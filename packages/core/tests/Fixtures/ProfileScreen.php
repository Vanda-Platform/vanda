<?php

declare(strict_types=1);

namespace Vanda\Tests\Fixtures;

use Vanda\Access\Impersonation;
use Vanda\Actions\Button;
use Vanda\Attributes\Action;
use Vanda\Fields\Select;
use Vanda\Fields\Upload;
use Vanda\Layouts\ActionRow;
use Vanda\Layouts\Avatar;
use Vanda\Layouts\Badge;
use Vanda\Layouts\EmptyState;
use Vanda\Layouts\Layout;
use Vanda\Layouts\TD;
use Vanda\Layouts\Tone;
use Vanda\Screen\Result;
use Vanda\Screen\Screen;

final class ProfileScreen extends Screen
{
    public User $user;

    public ?string $timezone = null;

    public ?int $photo = null;

    public function query(User $user): array
    {
        $this->user = $user;

        return [];
    }

    public function title(): string
    {
        return $this->user->name;
    }

    public function avatar(): ?Avatar
    {
        return Avatar::make($this->user->name)->image('/photos/1.png');
    }

    public function badge(): ?Badge
    {
        return Badge::make('Active', Tone::Success)->dot();
    }

    public function layout(): iterable
    {
        return [
            Select::make('timezone')->options([['value' => 'Europe/Berlin', 'label' => 'Europe/Berlin', 'hint' => 'UTC+01:00']]),
            Upload::make('photo')->avatar($this->user->name),
            Layout::actions([
                ActionRow::make('Sign in as')->description('See the panel as this user.')
                    ->buttons([Button::make('Sign in')->action($this->impersonate(...))]),
                ActionRow::make('Purge')->buttons([Button::make('Purge')->action($this->purge(...))]),
            ]),
            Layout::table()->rows([])->columns([TD::make('name')])->empty(EmptyState::make('Nobody yet')->icon('users')->description('Invite someone.')),
        ];
    }

    #[Action(validate: false)]
    public function impersonate(): Result
    {
        Impersonation::loginAs($this->user);

        return Result::redirectTo('/admin/showcase');
    }

    #[Action(validate: false)]
    #[\Vanda\Attributes\Can('platform.users.delete')]
    public function purge(): Result
    {
        return Result::toast('Purged');
    }
}
