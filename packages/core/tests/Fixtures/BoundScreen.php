<?php

declare(strict_types=1);

namespace Vanda\Tests\Fixtures;

use Vanda\Actions\Button;
use Vanda\Attributes\Action;
use Vanda\Fields\Input;
use Vanda\Layouts\Layout;
use Vanda\Screen\Result;
use Vanda\Screen\Screen;

/**
 * A button hidden for one record ("self", as a user's own account) and a modal with a required
 * field of its own: the server decides which button was pressed and what it validates.
 */
final class BoundScreen extends Screen
{
    /** @var list<string> actions that ran (static: survives a thrown request) */
    public static array $calls = [];

    /** @var array<string, mixed> */
    public array $quick = ['name' => ''];

    /** @var array<string, mixed> */
    public array $item = ['title' => 'Old'];

    /**
     * @return array<string, mixed>
     */
    public function query(string $record = 'other'): array
    {
        return ['record' => $record];
    }

    public function commandBar(): iterable
    {
        return [
            Button::make('Delete')->danger()->action($this->delete(...))->visible($this->prop('record') !== 'self'),
            Button::make('Save')->primary()->action($this->save(...)),
        ];
    }

    public function layout(): iterable
    {
        return [
            Input::make('item.title')->label('Title')->required(),
            Layout::modal('quick', 'Quick edit')
                ->schema([Input::make('quick.name')->label('Name')->required()])
                ->buttons([Button::make('Apply')->primary()->action($this->saveQuick(...))]),
        ];
    }

    #[Action]
    public function save(): Result
    {
        self::$calls[] = 'save';

        return Result::toast('Saved');
    }

    #[Action]
    public function saveQuick(): Result
    {
        self::$calls[] = 'saveQuick';

        return Result::toast('Applied');
    }

    #[Action]
    public function delete(): Result
    {
        self::$calls[] = 'delete';

        return Result::toast('Deleted');
    }
}
