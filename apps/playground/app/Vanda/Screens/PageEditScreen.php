<?php

namespace App\Vanda\Screens;

use App\Enums\PageStatus;
use App\Models\Page;
use App\Vanda\Forms\PageForm;
use Vanda\Actions\Button;
use Vanda\Attributes\Action;
use Vanda\Attributes\Can;
use Vanda\Fields\Code;
use Vanda\Fields\CodeLanguage;
use Vanda\Fields\Input;
use Vanda\Fields\Markdown;
use Vanda\Fields\RadioButtons;
use Vanda\Fields\RichText;
use Vanda\Fields\Select;
use Vanda\Fields\Upload;
use Vanda\Layouts\Layout;
use Vanda\Navigation\Crumb;
use Vanda\Schema\Expr;
use Vanda\Screen\Result;
use Vanda\Screen\Screen;

#[Can('platform.pages')]
final class PageEditScreen extends Screen
{
    public Page $page;

    public PageForm $form;

    public function query(Page $page): array
    {
        $this->page = $page;
        $this->form = PageForm::fromArray([
            ...$page->only(['title', 'slug', 'template']),
            'content' => (string) $page->content,
            'notes' => (string) $page->notes,
            'settings' => (string) $page->settings,
            'parent_id' => $page->exists ? $page->parent_id : (request()->integer('parent') ?: null),
            'status' => $page->status ?? PageStatus::Draft,
            'cover' => $page->exists ? $page->attachments('cover')->value('attachments.id') : null,
            'files' => $page->exists ? $page->attachments('files')->pluck('attachments.id')->all() : [],
        ]);

        return [];
    }

    public function title(): string
    {
        return $this->page->exists ? $this->page->title : 'Новая страница';
    }

    public function breadcrumbs(): iterable
    {
        // The pages above this one in the tree, each a way to its own form (a long trail folds into «…»).
        $ancestors = [];
        for ($parent = $this->page->parent; $parent !== null && count($ancestors) < 10; $parent = $parent->parent) {
            array_unshift($ancestors, Crumb::make($parent->title)->to(self::class, ['page' => $parent]));
        }

        return [Crumb::make('Сайт'), Crumb::make('Страницы')->to(PageListScreen::class), ...$ancestors, Crumb::make($this->title())];
    }

    public function commandBar(): iterable
    {
        return [
            Button::make('Сохранить')->icon('save')->primary()->hotkey('mod+s')->action($this->save(...)),
        ];
    }

    public function layout(): iterable
    {
        $slug = Input::make('form.slug')->label('Адрес')->prefix('/')->help('Латиница, цифры и дефис');

        return [
            Layout::section('Страница', 'Название, адрес и место в структуре сайта.')->schema([
                Layout::grid([
                    Input::make('form.title')->label('Название')->required(),
                    $this->page->exists ? $slug : $slug->computed(Expr::field('form.title')->slug()),
                ]),
                Layout::grid([
                    Select::make('form.parent_id')
                        ->label('Родительская страница')
                        ->placeholder('Верхний уровень')
                        ->tree(fn () => Page::query()->orderBy('position')->get(['id', 'title', 'parent_id']), label: 'title')
                        ->excludeBranch($this->page->id)
                        ->help('Страницу нельзя вложить в неё саму или в её потомков: такие пункты недоступны'),
                    Select::make('form.template')->label('Шаблон')->options(PageListScreen::TEMPLATES),
                ]),
                RadioButtons::make('form.status')->label('Статус')->options(PageStatus::class),
            ]),
            Layout::section('Содержимое', 'Текст страницы. Заголовки, списки, цитаты и ссылки — с панели над текстом.')->schema([
                RichText::make('form.content')->placeholder('Начните писать…'),
            ]),
            Layout::section('Медиа', 'Обложка для анонсов и файлы, которые можно скачать со страницы.')->schema([
                Layout::grid([
                    Upload::make('form.cover')->label('Обложка')->image()->maxSize(5)->group('cover'),
                    Upload::make('form.files')->label('Файлы')->multiple()->accept('.pdf,.xlsx,.docx,.zip')->maxFiles(10)->group('files'),
                ]),
            ]),
            Layout::section('Для разработчиков', 'Заметки редакции и настройки шаблона.')->schema([
                Markdown::make('form.notes')->label('Заметки')->placeholder('## Что важно знать'),
                Code::make('form.settings')->label('Настройки шаблона')->language(CodeLanguage::Json)->placeholder('{ }'),
            ]),
            Layout::section('История изменений', 'Кто и когда менял страницу.')->schema([
                Layout::activity($this->page)->limit(10),
            ])->visible($this->page->exists),
        ];
    }

    #[Action]
    public function save(): Result
    {
        $isNew = ! $this->page->exists;
        $this->page->fill($this->form->except('cover', 'files'));

        if ($isNew) {
            $this->page->position = Page::query()->where('parent_id', $this->form->parent_id)->max('position') + 1;
        }

        $this->page->save();
        $this->page->syncAttachments($this->form->cover, 'cover');
        $this->page->syncAttachments($this->form->files, 'files');

        // Saving keeps the user on the record; a new record opens at its own address.
        return $isNew ? Result::toast('Страница добавлена')->redirect(self::class, ['page' => $this->page]) : Result::toast('Страница сохранена');
    }
}
