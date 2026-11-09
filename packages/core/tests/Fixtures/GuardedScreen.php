<?php

declare(strict_types=1);

namespace Vanda\Tests\Fixtures;

use Vanda\Actions\Button;
use Vanda\Actions\Link;
use Vanda\Actions\ModalToggle;
use Vanda\Attributes\Action;
use Vanda\Attributes\Can;
use Vanda\Attributes\Updated;
use Vanda\Fields\Input;
use Vanda\Fields\Repeater;
use Vanda\Fields\Select;
use Vanda\Layouts\Layout;
use Vanda\Schema\Expr;
use Vanda\Screen\Result;
use Vanda\Screen\Screen;

/**
 * Parts hidden on the server, a field hidden in the browser, required Repeater cells and
 * buttons to forbidden things that ask to be visible.
 */
final class GuardedScreen extends Screen
{
    /** @var int how many times an #[Updated] hook ran (static: survives a thrown request) */
    public static int $hooks = 0;

    /** @var array<string, mixed> */
    public array $item = [
        'title' => 'Old',
        'kind' => 'plain',
        'discount' => '0',
        'internal' => 'Old',
        'audit' => 'Old',
        'rows' => [],
    ];

    public function title(): string
    {
        return 'Guarded';
    }

    public function commandBar(): iterable
    {
        return [
            Button::make('Purge')->danger()->action($this->purge(...))->visible(true),
            Button::make('Save')->primary()->action($this->save(...)),
        ];
    }

    public function layout(): iterable
    {
        return [
            Layout::section('Main')->schema([
                Input::make('item.title')->label('Title'),
                Select::make('item.kind')->label('Kind')->options(['plain' => 'Plain', 'sale' => 'Sale']),
                Input::make('item.discount')->label('Discount')->visibleWhen(Expr::field('item.kind')->eq('sale')),
            ]),
            Layout::section('Internal')->schema([Input::make('item.internal')->label('Internal')])->visible(false),
            Layout::card('Audit')->schema([Input::make('item.audit')->label('Audit')])->visible(false),
            Repeater::make('item.rows')->label('Rows')->fields([
                Input::make('name')->label('Name')->required(),
                Input::make('note')->label('Note'),
            ]),
            Layout::modal('purgeAll', 'Purge')->schema([Input::make('item.title')->label('Title')]),
            ModalToggle::make('Purge…')->id('toggle.purge')->modal('purgeAll')->load($this->purge(...))->visible(true),
            Link::make('Secured')->id('link.secured')->to(SecuredScreen::class)->visible(true),
        ];
    }

    #[Updated('item.title')]
    public function titleChanged(): void
    {
        self::$hooks++;
    }

    #[Action]
    public function save(): Result
    {
        return Result::toast(sprintf(
            'Saved %s, discount %s, internal %s, audit %s',
            $this->item['title'],
            $this->item['discount'],
            $this->item['internal'],
            $this->item['audit'],
        ));
    }

    #[Action]
    #[Can('platform.users.delete')]
    public function purge(): Result
    {
        return Result::toast('Purged');
    }
}
