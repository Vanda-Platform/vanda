<?php

declare(strict_types=1);

namespace Vanda\Tests\Fixtures;

use Vanda\Actions\Button;
use Vanda\Actions\ModalToggle;
use Vanda\Attributes\Action;
use Vanda\Fields\Input;
use Vanda\Fields\Switcher;
use Vanda\Fields\TextArea;
use Vanda\Layouts\Layout;
use Vanda\Screen\Result;
use Vanda\Screen\Screen;

final class ArticleScreen extends Screen
{
    public Article $article;

    /** @var array<string, mixed> */
    public array $form = [];

    public function query(Article $article): array
    {
        $this->article = $article;
        $this->form = $article->only(['title', 'body', 'published', 'token', 'views']);

        return [];
    }

    public function title(): string
    {
        return 'Статья';
    }

    public function commandBar(): iterable
    {
        return [
            ModalToggle::make('Подготовить')->id('prepare')->modal('prepared')->load($this->prepare(...)),
            Button::make('Отправить в 1С')->action($this->send(...)),
            Button::make('Сохранить')->primary()->action($this->save(...)),
        ];
    }

    public function layout(): iterable
    {
        return [
            Input::make('form.title')->label('Заголовок'),
            TextArea::make('form.body')->label('Текст'),
            Switcher::make('form.published')->label('Опубликована'),
            Input::make('form.token')->label('Токен'),
            Input::make('form.views')->label('Просмотры'),
            Layout::modal('prepared', 'Подготовка')->schema([]),
            Layout::activity($this->article)->limit(2),
        ];
    }

    #[Action]
    public function save(): Result
    {
        $this->article->update($this->form);

        return Result::toast('Сохранено');
    }

    #[Action(validate: false)]
    public function send(): Result
    {
        return Result::toast('Отправлено');
    }

    #[Action(validate: false)]
    public function prepare(): Result
    {
        return Result::refresh()->openModal('prepared');
    }
}
