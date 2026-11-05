<?php

namespace App\Vanda\Screens;

use App\Enums\Country;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Support\Carbon;
use Vanda\Layouts\Alert;
use Vanda\Layouts\Badge;
use Vanda\Layouts\Chart;
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
        $countries = User::query()->toBase()->whereNotNull('country')->selectRaw('country, count(*) as total')->groupBy('country')->pluck('total', 'country');

        return [
            Layout::metrics([
                Metric::make('Пользователи', User::query()->count())->delta('+2 за неделю')->trend([3, 4, 4, 5, 5, 6, User::query()->count()]),
                Metric::make('Активны', (int) ($byStatus[UserStatus::Active->value] ?? 0))->delta('+1')->trend([2, 3, 3, 3, 4, 4, 4]),
                Metric::make('Приглашены', $invited)->hint('Ждут первого входа'),
                Metric::make('Заблокированы', (int) ($byStatus[UserStatus::Blocked->value] ?? 0))->delta('+1', good: false)->trend([0, 0, 0, 1, 1, 1, 1]),
            ]),

            Layout::grid([
                $this->registrations(),
                Chart::ranking('Пользователи по странам')
                    ->labels($countries->keys()->map(fn (string $code) => Country::from($code)->label()))
                    ->series('Пользователи', $countries->values()),
            ], columns: 3),

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

    /**
     * New accounts per week for the last 12 weeks, next to the 12 weeks before.
     */
    private function registrations(): Chart
    {
        $start = now()->startOfWeek()->subWeeks(11);
        $dates = User::query()->where('created_at', '>=', $start->copy()->subWeeks(12))->pluck('created_at');
        $week = fn (Carbon $date, Carbon $from) => (int) floor($from->diffInDays($date) / 7);

        $current = array_fill(0, 12, 0);
        $previous = array_fill(0, 12, 0);
        foreach ($dates as $date) {
            $date = Carbon::parse($date);
            if ($date >= $start) {
                $current[min(11, $week($date, $start))]++;
            } else {
                $previous[min(11, $week($date, $start->copy()->subWeeks(12)))]++;
            }
        }

        return Chart::line('Регистрации по неделям')
            ->description(array_sum($current).' за 12 недель')
            ->labels(array_map(fn (int $index) => $start->copy()->addWeeks($index)->translatedFormat('j M'), range(0, 11)))
            ->series('Эти 12 недель', $current)
            ->series('Прошлые 12 недель', $previous)
            ->span(2);
    }
}
