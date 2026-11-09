<?php

namespace App\Vanda\Screens;

use App\Models\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Translation\MessageSelector;
use Vanda\Actions\Button;
use Vanda\Actions\Link;
use Vanda\Attributes\Action;
use Vanda\Attributes\Can;
use Vanda\Layouts\Badge;
use Vanda\Layouts\Layout;
use Vanda\Layouts\TD;
use Vanda\Layouts\Text;
use Vanda\Navigation\Crumb;
use Vanda\Panel;
use Vanda\Screen\Result;
use Vanda\Screen\Screen;

#[Can('platform.pages')]
final class PageListScreen extends Screen
{
    public const TEMPLATES = [
        'home' => 'Главная',
        'section' => 'Раздел',
        'page' => 'Страница',
        'catalog' => 'Каталог',
        'category' => 'Категория',
        'product' => 'Товар',
        'blog' => 'Блог',
    ];

    /** @var array<int, Page>|null */
    private ?array $pages = null;

    public function title(): string
    {
        return 'Страницы';
    }

    public function description(): string
    {
        return 'Структура сайта. Порядок и вложенность меняются перетаскиванием.';
    }

    public function breadcrumbs(): iterable
    {
        return [Crumb::make('Сайт'), Crumb::make('Страницы')];
    }

    public function commandBar(): iterable
    {
        return [Link::make('Создать страницу')->icon('plus')->primary()->to(PageEditScreen::class)];
    }

    public function layout(): iterable
    {
        return [
            Layout::tree()
                ->items(fn () => $this->pages())
                ->orderBy('position')
                ->searchable()
                ->expanded(1)
                ->rowLink(fn (Page $page) => Panel::url(PageEditScreen::class, ['page' => $page]))
                ->columns([
                    TD::make('title', 'Название'),
                    TD::make('path', 'Адрес')->render(fn (Page $page) => Text::make($page->path($this->pages()))->mono()->muted()),
                    TD::make('status', 'Статус')->render(fn (Page $page) => Badge::make($page->status->label(), $page->status->tone())->dot()),
                    TD::make('template', 'Шаблон')->render(fn (Page $page) => self::TEMPLATES[$page->template] ?? $page->template),
                    TD::make('updated_at', 'Изменена')->muted()->render(fn (Page $page) => $page->updated_at?->diffForHumans()),
                ])
                ->rowActions(fn (Page $page) => [
                    Link::make('Редактировать')->icon('pencil')->to(PageEditScreen::class, ['page' => $page]),
                    Link::make('Добавить вложенную')->icon('plus')->href(Panel::url(PageEditScreen::class).'?parent='.$page->id),
                    Button::make('Удалить')->icon('trash')->danger()
                        ->confirm($this->whatGoesWith($page), "Удалить страницу «{$page->title}»?", 'Удалить страницу')
                        ->action($this->delete(...), ['page' => $page]),
                ])
                ->reorderable($this->move(...))
                ->emptyText('Страниц пока нет'),
        ];
    }

    /**
     * Saves a drag-and-drop or Alt+arrow move: new parent and place among its children.
     */
    #[Action(validate: false)]
    public function move(int $id, ?int $parent, int $position): Result
    {
        $page = Page::query()->findOrFail($id);
        abort_if($parent !== null && in_array($parent, $page->branchKeys(), true), 422, 'Страницу нельзя вложить в неё саму.');

        DB::transaction(function () use ($page, $parent, $position): void {
            $renumber = static function (array $keys): void {
                foreach ($keys as $index => $key) {
                    Page::query()->whereKey($key)->update(['position' => $index]);
                }
            };
            $siblings = static fn (?int $parent) => Page::query()->where('parent_id', $parent)->whereKeyNot($page->id)->orderBy('position')->pluck('id')->all();

            $previous = $page->parent_id;
            $keys = $siblings($parent);
            array_splice($keys, max(0, min($position, count($keys))), 0, [$page->id]);

            $page->update(['parent_id' => $parent]);
            $renumber($keys);
            if ($previous !== $parent) {
                $renumber($siblings($previous));
            }
        });

        return Result::toast('Порядок сохранён');
    }

    #[Action(validate: false)]
    public function delete(Page $page): Result
    {
        Page::query()->whereKey($page->branchKeys())->delete();

        return Result::toast("Удалена страница «{$page->title}»");
    }

    /** What else goes with a page: the pages under it, counted from the tree already loaded. */
    private function whatGoesWith(Page $page): string
    {
        $children = [];
        foreach ($this->pages() as $item) {
            $children[$item->parent_id][] = $item->id;
        }
        $count = 0;
        for ($queue = $children[$page->id] ?? []; $queue !== []; $count++) {
            array_push($queue, ...($children[array_shift($queue)] ?? []));
        }

        return $count === 0
            ? 'Страница будет удалена без возможности восстановления.'
            : sprintf('Вместе с ней удалятся %d %s. Восстановить их нельзя.', $count, (new MessageSelector)->choose('вложенная страница|вложенные страницы|вложенных страниц', $count, 'ru'));
    }

    /**
     * @return array<int, Page>
     */
    private function pages(): array
    {
        return $this->pages ??= Page::query()->get()->keyBy('id')->all();
    }
}
