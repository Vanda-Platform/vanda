<?php

namespace App\Vanda\Screens;

use App\Enums\UserStatus;
use App\Models\User;
use App\Vanda\Forms\QuickUserForm;
use Illuminate\Database\Eloquent\Builder;
use Vanda\Access\Role;
use Vanda\Actions\Button;
use Vanda\Actions\Link;
use Vanda\Actions\ModalToggle;
use Vanda\Attributes\Action;
use Vanda\Attributes\Can;
use Vanda\Attributes\Locked;
use Vanda\Fields\Input;
use Vanda\Fields\RadioButtons;
use Vanda\Layouts\Badge;
use Vanda\Layouts\Filter;
use Vanda\Layouts\Layout;
use Vanda\Layouts\Persona;
use Vanda\Layouts\TD;
use Vanda\Layouts\View;
use Vanda\Panel;
use Vanda\Screen\Result;
use Vanda\Screen\Screen;

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
                ->query(fn () => User::query()->with(['roles', 'attachments']))
                ->searchable(['name', 'email'], 'Поиск по имени или email')
                ->views([
                    View::make('Все'),
                    View::make('Администраторы', ['role' => 'admin']),
                    View::make('Приглашённые', ['status' => UserStatus::Invited->value]),
                    View::make('Заблокированные', ['status' => UserStatus::Blocked->value]),
                ])
                ->filters([
                    Filter::select('role', 'Роль')
                        ->options(fn () => Role::query()->pluck('name', 'slug'))
                        ->apply(fn (Builder $query, string $slug) => $query->whereHas('roles', fn (Builder $roles) => $roles->where('slug', $slug))),
                    Filter::select('status', 'Статус')->options(UserStatus::class),
                    Filter::dateRange('created', 'Создан')->column('created_at'),
                ])
                ->defaultSort('id', 'desc')
                ->paginate(10, [25, 50])
                ->rowLink(fn (User $user) => Panel::url(UserEditScreen::class, ['user' => $user]))
                ->exportable('users.csv')
                ->emptyText('Никого не нашлось')
                ->columns([
                    TD::make('name', 'Пользователь')->sortable()->render(
                        fn (User $user) => Persona::make($user->name)->subtitle($user->email)->image($user->avatarUrl()),
                    ),
                    TD::make('roles', 'Роли')->hideable()->render(fn (User $user) => $user->roles->pluck('name')->join(', ') ?: '—'),
                    TD::make('country', 'Страна')->defaultHidden()->render(fn (User $user) => $user->country?->label()),
                    TD::make('status', 'Статус')->render(
                        fn (User $user) => Badge::make($user->status->label(), $user->status->tone())->dot(),
                    ),
                    TD::make('created_at', 'Создан')->sortable()->muted()->hideable()->render(fn (User $user) => $user->created_at?->translatedFormat('j M Y')),
                ])
                ->rowActions(fn (User $user) => [
                    Link::make('Редактировать')->icon('pencil')->to(UserEditScreen::class, ['user' => $user]),
                    ModalToggle::make('Быстро изменить')->icon('settings')->modal('quickEdit')->load($this->loadQuick(...), ['user' => $user]),
                    Button::make('Удалить')
                        ->icon('trash')
                        ->danger()
                        ->confirm('Учётная запись будет удалена без возможности восстановления.', "Удалить пользователя «{$user->name}»?", 'Удалить пользователя')
                        ->action($this->delete(...), ['user' => $user]),
                ])
                ->bulkActions([
                    Button::make('Удалить')
                        ->icon('trash')
                        ->danger()
                        ->confirm('Учётные записи будут удалены без возможности восстановления.', 'Удалить выбранных пользователей (:count)?', 'Удалить (:count)')
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

    #[Action(validate: false)]
    public function delete(User $user): Result
    {
        abort_if($user->is(auth()->user()), 422, 'Нельзя удалить самого себя.');
        $user->delete();

        return Result::toast("Удалён: {$user->name}");
    }

    /**
     * @param  list<string>  $ids
     */
    #[Action(validate: false)]
    public function deleteMany(array $ids): Result
    {
        $count = User::query()->whereKey($ids)->whereKeyNot(auth()->id())->delete();

        return Result::toast("Удалено пользователей: {$count}");
    }
}
