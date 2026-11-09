<?php

declare(strict_types=1);

namespace Vanda\Tests\Fixtures;

use Vanda\Actions\Button;
use Vanda\Actions\ModalToggle;
use Vanda\Attributes\Action;
use Vanda\Fields\Input;
use Vanda\Fields\Repeater;
use Vanda\Fields\Select;
use Vanda\Layouts\Layout;
use Vanda\Schema\Expr;
use Vanda\Screen\Result;
use Vanda\Screen\Screen;

/**
 * The mistakes an agent makes most, each of which used to fail quietly in the browser
 * (SchemaCheckTest). Paths inside arrays, models and Repeater rows are left alone.
 */
final class BrokenScreen extends Screen
{
    public UserForm $form;

    public ArticleStatus $status = ArticleStatus::Draft;

    /** @var array<string, mixed> */
    public array $settings = [];

    public function query(): array
    {
        $this->form = new UserForm;

        return [];
    }

    public function commandBar(): iterable
    {
        return [
            ModalToggle::make('Quick edit')->modal('quickEdt'),
            Button::make('Save')->icon('trashcan')->primary()->action($this->save(...)),
        ];
    }

    public function layout(): iterable
    {
        return [
            Layout::section('Profile')->schema([
                Input::make('form.emial')->label('Email'),
                Input::make('from.name')->label('Name'),
                Input::make('status.label')->label('Status'),
                Select::make('form.city')->label('City')->options([1 => 'Berlin'])->dependsOn('form.contry'),
                Input::make('form.vat')->label('VAT')->visibleWhen(Expr::field('form.contry')->eq('DE')),
                // Fine: the shape of an array is not declared, Repeater rows are relative.
                Input::make('settings.mail.from')->label('Sender'),
                Repeater::make('settings.rows')->label('Rows')->fields([Input::make('anything')->label('Anything')]),
            ]),
            Layout::modal('quickEdit', 'Quick edit'),
        ];
    }

    #[Action]
    public function save(): Result
    {
        return Result::toast('Saved');
    }
}
