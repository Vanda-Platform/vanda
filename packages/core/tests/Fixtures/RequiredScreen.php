<?php

declare(strict_types=1);

namespace Vanda\Tests\Fixtures;

use Vanda\Actions\Button;
use Vanda\Attributes\Action;
use Vanda\Fields\Input;
use Vanda\Screen\Result;
use Vanda\Screen\Screen;

final class RequiredScreen extends Screen
{
    /** @var array<string, mixed> */
    public array $item = ['company' => '', 'city' => ''];

    public function commandBar(): iterable
    {
        return [Button::make('Save')->primary()->action($this->save(...))];
    }

    public function layout(): iterable
    {
        return [
            Input::make('item.company')->label('Company')->required(),
            Input::make('item.city')->label('City'),
        ];
    }

    #[Action]
    public function save(): Result
    {
        return Result::toast('Saved');
    }
}
