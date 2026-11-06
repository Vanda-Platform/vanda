<?php

declare(strict_types=1);

namespace Vanda\Tests\Fixtures;

use Vanda\Actions\Button;
use Vanda\Actions\DropDown;
use Vanda\Actions\Link;
use Vanda\Attributes\Action;
use Vanda\Fields\Code;
use Vanda\Fields\CodeLanguage;
use Vanda\Fields\Markdown;
use Vanda\Fields\RichText;
use Vanda\Layouts\Chart;
use Vanda\Screen\Result;
use Vanda\Screen\Screen;

final class EditorsScreen extends Screen
{
    public string $content = '';

    public string $article = '<p><span style="color: red">Old</span> markup</p>';

    public string $notes = '';

    public string $settings = '';

    public function commandBar(): iterable
    {
        return [
            DropDown::make('More')->list([
                Link::make('Docs')->href('https://example.com/docs'),
                Button::make('Purge')->danger()->action($this->purge(...)),
            ]),
            DropDown::make('Forbidden only')->list([Button::make('Purge')->action($this->purge(...))]),
        ];
    }

    public function layout(): iterable
    {
        return [
            RichText::make('content')->minHeight(120),
            RichText::make('article')->embeds(['rutube.ru']),
            Markdown::make('notes'),
            Code::make('settings')->language(CodeLanguage::Json)->lineNumbers(false),
            Chart::line('Revenue')->labels(['Mon', 'Tue'])->series('This week', [1, 2.5])->unit('₽')->span(2),
        ];
    }

    #[Action(validate: false)]
    #[\Vanda\Attributes\Can('platform.users.delete')]
    public function purge(): Result
    {
        return Result::toast('Purged');
    }
}
