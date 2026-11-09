<?php

declare(strict_types=1);

namespace Vanda\Tests\Fixtures;

use DateTimeImmutable;
use Vanda\Actions\Button;
use Vanda\Actions\ModalToggle;
use Vanda\Attributes\Action;
use Vanda\Attributes\Can;
use Vanda\Attributes\Locked;
use Vanda\Fields\Input;
use Vanda\Layouts\FilesLayout;
use Vanda\Layouts\Layout;
use Vanda\Screen\Result;
use Vanda\Screen\Screen;

final class FilesScreen extends Screen
{
    #[Locked]
    public ?int $fileId = null;

    public string $q = '';

    public string $fileName = '';

    public function layout(): iterable
    {
        return [
            Input::make('q')->label('Search'),
            Layout::files()->id('files')
                ->folder('Certificates', '/admin/files/3', meta: '12 files')
                ->folder('Old', '/admin/files/4', hint: 'in «Archive»')
                ->file('7', 'Price list.PDF', url: '/storage/price.pdf', mime: 'application/pdf', size: 52_000, date: new DateTimeImmutable('2026-03-12'))
                ->file('8', 'banner.jpg', url: '/storage/banner.jpg', mime: 'image/jpeg', size: 3_565_158)
                ->file('9', 'logo.svg', url: '/storage/logo.svg', mime: 'image/svg+xml', hint: 'in «Brand»')
                ->file('10', 'secret.zip', size: 820)
                ->fileAction(fn (string $key) => match ($key) {
                    '9' => null,
                    '10' => Button::make('Purge')->action($this->purge(...), ['id' => (int) $key]),
                    default => ModalToggle::make('Open')->modal('file')->load($this->loadFile(...), ['id' => (int) $key]),
                })
                ->selected('8')
                ->layout(FilesLayout::List)
                ->dependsOn('q'),
            Layout::files()->id('empty')->emptyText('Nothing in the folder'),
            Layout::modal('file', 'File')->schema([Input::make('fileName')->label('Name')]),
        ];
    }

    #[Action(validate: false)]
    public function loadFile(int $id): Result
    {
        $this->fileId = $id;
        $this->fileName = "file {$id}";

        return Result::refresh()->openModal('file');
    }

    #[Action(validate: false)]
    #[Can('platform.users.delete')]
    public function purge(int $id): Result
    {
        return Result::toast("Purged {$id}");
    }
}
