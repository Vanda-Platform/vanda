<?php

declare(strict_types=1);

namespace Vanda\Tests\Fixtures;

use Vanda\Attributes\Action;
use Vanda\Fields\Upload;
use Vanda\Screen\Result;
use Vanda\Screen\Screen;

final class UploadScreen extends Screen
{
    public ?int $cover = null;

    /** @var list<int> */
    public array $files = [];

    public function layout(): iterable
    {
        return [
            Upload::make('cover')->image()->maxSize(1),
            Upload::make('files')->multiple()->accept('.pdf,.txt')->maxFiles(2)->group('docs'),
        ];
    }

    #[Action]
    public function save(): Result
    {
        return Result::refresh();
    }
}
