<?php

namespace App\Vanda\Screens;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Support\Carbon;
use Vanda\Layouts\Alert;
use Vanda\Layouts\Badge;
use Vanda\Layouts\Layout;
use Vanda\Layouts\Metric;
use Vanda\Layouts\Persona;
use Vanda\Layouts\TD;
use Vanda\Layouts\Tone;
use Vanda\Screen\Screen;
use Vanda\Vanda;

final class DashboardScreen extends Screen
{
    public function title(): string
    {
        return 'Обзор';
    }

    public function description(): string
    {
        return 'Сводка по учётным записям панели';
    }

    public function layout(): iterable
    {
        $byStatus = User::query()->toBase()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $invited = (int) ($byStatus[UserStatus::Invited->value] ?? 0);

        return [
            Layout::metrics([
                Metric::make('Пользователи', User::query()->count())->delta('+2 за неделю')->trend([3, 4, 4, 5, 5, 6, User::query()->count()]),
                Metric::make('Активны', (int) ($byStatus[UserStatus::Active->value] ?? 0))->delta('+1')->trend([2, 3, 3, 3, 4, 4, 4]),
                Metric::make('Приглашены', $invited)->hint('Ждут первого входа'),
                Metric::make('Заблокированы', (int) ($byStatus[UserStatus::Blocked->value] ?? 0))->delta('+1', good: false)->trend([0, 0, 0, 1, 1, 1, 1]),
            ]),

            Alert::make("Приглашений без ответа: {$invited}. Напомните коллегам войти в панель.")->title('Обратите внимание.')->tone(Tone::Warning)->visible($invited > 0),

            Layout::section('Новые пользователи', 'Последние пять учётных записей')->schema([
                Layout::table()
                    ->rows(fn () => User::query()->latest('id')->limit(5)->get())
                    ->rowLink(fn (User $user) => Vanda::url(UserEditScreen::class, ['user' => $user]))
                    ->columns([
                        TD::make('name', 'Пользователь')->render(fn (User $user) => Persona::make($user->name)->subtitle($user->email)),
                        TD::make('status', 'Статус')->render(fn (User $user) => Badge::make($user->status->label(), $user->status->tone())->dot()),
                        TD::make('created_at', 'Создан')->alignRight()->muted()->render(fn (User $user) => Carbon::parse($user->created_at)->diffForHumans()),
                    ]),
            ]),
        ];
    }
}
