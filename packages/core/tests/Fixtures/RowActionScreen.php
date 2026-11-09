<?php

declare(strict_types=1);

namespace Vanda\Tests\Fixtures;

use Vanda\Actions\Button;
use Vanda\Actions\Link;
use Vanda\Actions\ModalToggle;
use Vanda\Attributes\Action;
use Vanda\Attributes\Can;
use Vanda\Attributes\Locked;
use Vanda\Fields\Input;
use Vanda\Layouts\Layout;
use Vanda\Layouts\TD;
use Vanda\Screen\Result;
use Vanda\Screen\Screen;

final class RowActionScreen extends Screen
{
    /** @var list<int> */
    public static array $purged = [];

    #[Locked]
    public ?int $seoId = null;

    public string $seoTitle = '';

    public function layout(): iterable
    {
        return [
            Layout::table()
                ->rows(fn () => User::query()->orderBy('id')->get())
                ->columns([TD::make('name', 'Name'), TD::make('email', 'Email')])
                ->rowAction(fn (User $user) => ModalToggle::make('Open')->modal('seo')->load($this->loadSeo(...), ['id' => $user->id])),
            Layout::tree()
                ->items(TreeScreen::PAGES)
                ->columns([TD::make('title', 'Title')])
                ->rowAction(fn (array $page) => $page['id'] === 6 ? null : Button::make('Purge')->confirm('Gone for good.')->action($this->purge(...), ['id' => $page['id']])),
            Layout::table()
                ->id('links')
                ->rows([['id' => 7, 'name' => 'Docs']])
                ->columns([TD::make('name', 'Name')])
                ->rowAction(fn (array $row) => Link::make('Open')->href('/docs/'.$row['id'])),
            Layout::modal('seo', 'SEO')
                ->schema([Input::make('seoTitle')->label('Title')])
                ->buttons([Button::make('Save')->primary()->action($this->saveSeo(...))]),
        ];
    }

    #[Action(validate: false)]
    public function loadSeo(int $id): Result
    {
        $this->seoId = $id;
        $this->seoTitle = (string) User::query()->findOrFail($id)->name;

        return Result::refresh()->openModal('seo');
    }

    #[Action]
    public function saveSeo(): Result
    {
        return Result::toast('Saved');
    }

    #[Action(validate: false)]
    #[Can('platform.users.delete')]
    public function purge(int $id): Result
    {
        self::$purged[] = $id;

        return Result::toast('Purged');
    }
}
