<?php

declare(strict_types=1);

namespace Vanda\Tests\Fixtures;

use Vanda\Actions\Button;
use Vanda\Attributes\Action;
use Vanda\Fields\Crop;
use Vanda\Fields\Input;
use Vanda\Fields\Repeater;
use Vanda\Fields\RichText;
use Vanda\Fields\Upload;
use Vanda\Fields\UploadTarget;
use Vanda\Screen\Result;
use Vanda\Screen\Screen;

final class UploadScreen extends Screen
{
    public ?int $cover = null;

    /** @var list<int> */
    public array $files = [];

    public ?string $photo = '/storage/legacy/old-photo.jpg';

    public ?int $any = null;

    public ?int $logo = null;

    public string $body = '';

    public string $note = '';

    /** @var list<array{title?: string, image?: int}> */
    public array $slides = [];

    public function commandBar(): iterable
    {
        return [Button::make('Save')->primary()->action($this->save(...))];
    }

    public function layout(): iterable
    {
        return [
            Upload::make('cover')->image()->maxSize(1),
            Upload::make('files')->multiple()->accept('.pdf,.txt')->maxFiles(2)->group('docs'),
            Upload::make('photo')->image()->target(UploadTarget::RelativeUrl)->directory('photos')
                ->crop(Crop::size(556, 322)->label('Preview'), Crop::ratio(1, 1)->label('Square')->maxWidth(1200)->minSize(300, 300))
                ->customCrop(),
            Upload::make('any'),
            Upload::make('logo')->accept('.svg'),
            RichText::make('body')->uploads(1),
            RichText::make('note'),
            Repeater::make('slides')->fields([Input::make('title'), Upload::make('image')->image()]),
        ];
    }

    #[Action]
    public function save(): Result
    {
        return Result::refresh();
    }
}
