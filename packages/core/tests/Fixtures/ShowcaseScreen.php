<?php

declare(strict_types=1);

namespace Vanda\Tests\Fixtures;

use Vanda\Actions\Button;
use Vanda\Actions\ModalToggle;
use Vanda\Attributes\Action;
use Vanda\Fields\Input;
use Vanda\Fields\KeyValue;
use Vanda\Fields\Password;
use Vanda\Fields\PermissionsField;
use Vanda\Fields\Repeater;
use Vanda\Layouts\Layout;
use Vanda\Layouts\TD;
use Vanda\Screen\Result;
use Vanda\Screen\Screen;

final class ShowcaseScreen extends Screen
{
    public ShowcaseForm $form;

    public QuickForm $quick;

    /** @var list<string> */
    public array $deleted = [];

    public function query(): array
    {
        $this->form = new ShowcaseForm;
        $this->quick = new QuickForm;

        return [];
    }

    public function commandBar(): iterable
    {
        return [Button::make('Save')->primary()->action($this->save(...))];
    }

    public function layout(): iterable
    {
        return [
            Layout::tabs([
                'Main' => [Input::make('form.name')->label('Name'), Password::make('form.token')->label('Token')],
                'Specs' => [
                    Repeater::make('form.specs')->fields([
                        Input::make('name')->label('Spec')->rules('required'),
                        Input::make('value')->label('Value'),
                    ])->max(3),
                ],
            ]),
            KeyValue::make('form.utm')->label('UTM'),
            PermissionsField::make('form.permissions')->label('Permissions'),
            Layout::modal('quick', 'Quick edit')
                ->schema([Input::make('quick.title')->label('Title')])
                ->buttons([Button::make('Save quick')->primary()->action($this->saveQuick(...))]),
            ModalToggle::make('Open')->modal('quick')->load($this->loadQuick(...), ['id' => 7]),
            Layout::table()
                ->query(fn () => User::query())
                ->searchable(['name', 'email'])
                ->paginate(10)
                ->columns([TD::make('name', 'Name')->sortable(), TD::make('email', 'Email')])
                ->bulkActions([Button::make('Delete selected')->danger()->action($this->deleteMany(...))]),
        ];
    }

    #[Action]
    public function save(): Result
    {
        return Result::toast('Saved');
    }

    #[Action]
    public function saveQuick(): Result
    {
        return Result::toast('Quick saved: '.$this->quick->title);
    }

    #[Action(validate: false)]
    public function loadQuick(int $id): Result
    {
        $this->quick->title = 'Item '.$id;

        return Result::refresh()->openModal('quick');
    }

    /**
     * @param list<string> $ids
     */
    #[Action(validate: false)]
    public function deleteMany(array $ids): Result
    {
        $this->deleted = $ids;
        User::query()->whereKey($ids)->delete();

        return Result::toast('Deleted '.count($ids));
    }
}
