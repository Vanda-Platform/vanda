<?php

namespace App\Vanda\Screens;

use App\Enums\UserStatus;
use App\Models\User;
use App\Vanda\Forms\QuickUserForm;
use Vanda\Actions\Button;
use Vanda\Actions\Link;
use Vanda\Actions\ModalToggle;
use Vanda\Actions\Variant;
use Vanda\Attributes\Action;
use Vanda\Attributes\Can;
use Vanda\Attributes\Locked;
use Vanda\Fields\Input;
use Vanda\Fields\RadioButtons;
use Vanda\Layouts\Badge;
use Vanda\Layouts\Layout;
use Vanda\Layouts\Persona;
use Vanda\Layouts\TD;
use Vanda\Screen\Result;
use Vanda\Screen\Screen;
use Vanda\Vanda;

#[Can('platform.users')]
final class UserListScreen extends Screen
{
    public QuickUserForm $quick;

    #[Locked]
    public ?int $quickUserId = null;

    public function query(): array
    {
        $this->quick = new QuickUserForm;

        return [];
    }

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
                ->query(fn () => User::query()->with('roles'))
                ->searchable(['name', 'email'], 'Имя или email')
                ->defaultSort('id', 'desc')
                ->paginate(10)
                ->rowLink(fn (User $user) => Vanda::url(UserEditScreen::class, ['user' => $user]))
                ->emptyText('Никого не нашлось')
                ->columns([
                    TD::make('name', 'Пользователь')->sortable()->render(
                        fn (User $user) => Persona::make($user->name)->subtitle($user->email),
                    ),
                    TD::make('roles', 'Роли')->render(fn (User $user) => $user->roles->pluck('name')->join(', ') ?: '—'),
                    TD::make('status', 'Статус')->render(
                        fn (User $user) => Badge::make($user->status->label(), $user->status->tone())->dot(),
                    ),
                    TD::make('created_at', 'Создан')->sortable()->muted()->render(fn (User $user) => $user->created_at?->translatedFormat('j M Y')),
                    TD::make('actions', '')->alignRight()->render(
                        fn (User $user) => ModalToggle::make('Быстро изменить')
                            ->variant(Variant::Ghost)
                            ->icon('pencil')
                            ->modal('quickEdit')
                            ->load($this->loadQuick(...), ['user' => $user]),
                    ),
                ])
                ->bulkActions([
                    Button::make('Удалить')
                        ->icon('trash')
                        ->danger()
                        ->confirm('Выбранные пользователи будут удалены без возможности восстановления.', 'Удалить пользователей?', 'Удалить')
                        ->action($this->deleteMany(...)),
                ]),

            Layout::modal('quickEdit', 'Быстрое редактирование')
                ->description('Имя и статус без перехода в карточку')
                ->schema([
                    Input::make('quick.name')->label('Имя'),
                    RadioButtons::make('quick.status')->label('Статус')->options(UserStatus::class),
                ])
                ->buttons([Button::make('Сохранить')->primary()->action($this->saveQuick(...))]),
        ];
    }

    #[Action(validate: false)]
    public function loadQuick(User $user): Result
    {
        $this->quickUserId = $user->id;
        $this->quick = QuickUserForm::fromArray($user->only(['name', 'status']));

        return Result::refresh()->openModal('quickEdit');
    }

    #[Action]
    public function saveQuick(): Result
    {
        $user = User::query()->findOrFail($this->quickUserId);
        $user->update($this->quick->toArray());

        return Result::toast("Сохранено: {$user->name}");
    }

    /**
     * @param list<string> $ids
     */
    #[Action(validate: false)]
    public function deleteMany(array $ids): Result
    {
        $count = User::query()->whereKey($ids)->whereKeyNot(auth()->id())->delete();

        return Result::toast("Удалено пользователей: {$count}");
    }
}
