<?php

namespace App\Vanda\Screens;

use App\Models\Page;
use App\Models\User;
use App\Vanda\Forms\ShowcaseForm;
use Vanda\Actions\Button;
use Vanda\Attributes\Action;
use Vanda\Fields\CheckBox;
use Vanda\Fields\Code;
use Vanda\Fields\Crop;
use Vanda\Fields\CodeLanguage;
use Vanda\Fields\Color;
use Vanda\Fields\DateRange;
use Vanda\Fields\DateTimer;
use Vanda\Fields\Input;
use Vanda\Fields\KeyValue;
use Vanda\Fields\Label;
use Vanda\Fields\Map;
use Vanda\Fields\Matrix;
use Vanda\Fields\Relation;
use Vanda\Fields\TimeZone;
use Vanda\Fields\Utm;
use Vanda\Fields\Markdown;
use Vanda\Fields\NumberRange;
use Vanda\Fields\Password;
use Vanda\Fields\Radio;
use Vanda\Fields\RadioButtons;
use Vanda\Fields\Range;
use Vanda\Fields\Repeater;
use Vanda\Fields\RichText;
use Vanda\Fields\Select;
use Vanda\Fields\Switcher;
use Vanda\Fields\TextArea;
use Vanda\Fields\Upload;
use Vanda\Fields\UploadTarget;
use Vanda\Actions\Link;
use Vanda\Layouts\ActionRow;
use Vanda\Layouts\Alert;
use Vanda\Layouts\EmptyState;
use Vanda\Layouts\Layout;
use Vanda\Layouts\Sight;
use Vanda\Schema\Expr;
use Vanda\Screen\Result;
use Vanda\Screen\Screen;

/**
 * Every field and layout of Vanda on one screen: a live reference for people and agents.
 */
final class ShowcaseScreen extends Screen
{
    public ShowcaseForm $form;

    public function query(): array
    {
        $this->form = new ShowcaseForm;

        return [];
    }

    public function title(): string
    {
        return 'Витрина компонентов';
    }

    public function description(): string
    {
        return 'Все поля и лейауты Vanda. Alt+клик по любому элементу покажет строку PHP, где он создан.';
    }

    public function commandBar(): iterable
    {
        return [Button::make('Сохранить')->icon('save')->primary()->hotkey('mod+s')->action($this->save(...))];
    }

    public function layout(): iterable
    {
        return [
            Alert::make('Значения не сохраняются в базу: кнопка «Сохранить» только проверяет форму и показывает результат.')->title('Песочница.'),

            Layout::tabs([
                'Текст и выбор' => [
                    Layout::section('Текст', 'Input, TextArea, Password. Адрес страницы считается из заголовка прямо в браузере.')->schema([
                        Layout::grid([
                            Input::make('form.title')->label('Заголовок')->required()->maxLength(120),
                            Input::make('form.slug')->label('Адрес страницы')->prefix('/blog/')->computed(Expr::field('form.title')->slug()),
                            Input::make('form.price')->label('Цена')->suffix('₽')->help('Число, можно с копейками'),
                            Password::make('form.password')->label('Пароль')->strength()->help('Секретное поле: значение не возвращается в браузер'),
                        ], columns: 2),
                        TextArea::make('form.description')->label('Описание')->maxLength(500),
                    ]),
                    Layout::section('Выбор', 'Select (один и несколько), Radio, RadioButtons, CheckBox, Switcher.')->schema([
                        Layout::grid([
                            Select::make('form.category')->label('Категория')->options(['beans' => 'Кофе в зёрнах', 'ground' => 'Молотый кофе', 'capsules' => 'Кофе в капсулах']),
                            Select::make('form.tags')->label('Метки')->multiple()->options(['new' => 'Новинка', 'hit' => 'Хит продаж', 'sale' => 'Скидка', 'eco' => 'Эко']),
                            Radio::make('form.delivery')->label('Доставка')->options(['courier' => 'Курьером', 'pickup' => 'Самовывоз', 'post' => 'Почтой']),
                            RadioButtons::make('form.state')->label('Статус')->options(['draft' => 'Черновик', 'review' => 'На проверке', 'published' => 'Опубликован']),
                        ], columns: 2),
                        CheckBox::make('form.featured')->label('Показывать на главной'),
                        Switcher::make('form.published')->label('Опубликовано')->help('Товар виден в каталоге'),
                    ]),
                ],
                'Даты и числа' => [
                    Layout::section('Даты', 'DateTimer с временем и DateRange, локализованы по языку панели.')->schema([
                        Layout::grid([
                            DateTimer::make('form.startsAt')->label('Начало акции')->withTime(),
                            DateRange::make('form.period')->label('Период отчёта'),
                        ], columns: 2),
                    ]),
                    Layout::section('Числа и цвет', 'Range, NumberRange и Color с палитрой.')->schema([
                        Layout::grid([
                            Range::make('form.discount')->label('Скидка')->between(0, 50, 5)->suffix('%'),
                            NumberRange::make('form.priceRange')->label('Цена')->suffix('₽'),
                            Color::make('form.color')->label('Цвет бренда')->presets(['#12805C', '#5B4BD5', '#2563EB', '#C2410C', '#BE185D']),
                        ], columns: 2),
                    ]),
                ],
                'Списки' => [
                    Layout::section('Повторитель', 'Строки с одинаковыми полями. Правила полей проверяются для каждой строки.')->schema([
                        Repeater::make('form.specs')->label('Характеристики')->fields([
                            Input::make('name')->label('Название')->rules('required'),
                            Input::make('value')->label('Значение'),
                        ])->addLabel('Добавить характеристику')->max(6),
                    ]),
                    Layout::section('Ключ — значение', 'Пары для UTM-меток, заголовков, настроек.')->schema([
                        KeyValue::make('form.utm')->label('UTM-метки')->labels('Ключ', 'Значение', 'Добавить пару'),
                    ]),
                ],
                'Редакторы и файлы' => [
                    Layout::section('Выбор из дерева', 'Отметка раздела выбирает всё внутри; весь раздел показан одной меткой.')->schema([
                        Select::make('form.categories')->label('Разделы каталога')->multiple()
                            ->tree(fn () => Page::query()->orderBy('position')->get(['id', 'title', 'parent_id']), label: 'title'),
                    ]),
                    Layout::section('Форматированный текст', 'Tiptap: цвета, таблицы, картинки с компьютера, видео, поиск и «/» для вставки блоков. HTML чистится на сервере.')->schema([
                        RichText::make('form.content')->label('Описание')->uploads(5)->embeds(['rutube.ru', 'youtube.com']),
                    ]),
                    Layout::section('Markdown и код', 'CodeMirror 6, языки подгружаются по требованию.')->schema([
                        Markdown::make('form.notes')->label('Заметки'),
                        Code::make('form.config')->label('settings.json')->language(CodeLanguage::Json),
                    ]),
                    Layout::section('Загрузка файлов', 'Файлы ложатся в таблицу attachments, как в Orchid.')->schema([
                        Upload::make('form.attachments')->label('Вложения')->multiple()->maxFiles(5)->accept('image/*,.pdf,.xlsx'),
                    ]),
                    Layout::section('Картинка с обрезкой', 'Размеры заданы в коде; свои размеры пользователь вводит в окне обрезки, браузер их запоминает. В поле хранится URL, как Cropper ->targetRelativeUrl().')->schema([
                        Upload::make('form.cover')->label('Обложка')->image()->maxSize(5)
                            ->target(UploadTarget::RelativeUrl)
                            ->crop(
                                Crop::size(556, 322)->label('Превью'),
                                Crop::ratio(16, 9)->label('Баннер')->maxWidth(1920)->minSize(640, 360),
                                Crop::ratio(1, 1)->label('Квадрат')->maxWidth(1200),
                            )
                            ->customCrop(),
                        Repeater::make('form.slides')->label('Слайды')->addLabel('Добавить слайд')->fields([
                            Input::make('title')->label('Подпись'),
                            Upload::make('image')->label('Картинка')->image()->crop(Crop::size(1200, 400)->label('Слайд')),
                        ]),
                    ]),
                ],
                'Из Orchid' => [
                    Layout::section('Выбор записей', 'Relation ищет по таблице на сервере, пока пользователь печатает: годится для тысяч записей.')->schema([
                        Relation::make('form.manager')->label('Менеджер')->model(User::class, 'name')->searchColumns('name', 'email')
                            ->display(fn (User $user) => "{$user->name} · {$user->email}"),
                        Relation::make('form.watchers')->label('Наблюдатели')->model(User::class, 'name')->multiple(),
                        Select::make('form.labels')->label('Метки')->multiple()->allowCustom()
                            ->options(['кофе' => 'кофе', 'чай' => 'чай', 'десерты' => 'десерты'])
                            ->help('Можно выбрать из списка или добавить свою'),
                        TimeZone::make('form.timezone')->label('Часовой пояс'),
                    ]),
                    Layout::section('Таблица значений', 'Matrix — Repeater в виде таблицы.')->schema([
                        Matrix::make('form.prices', ['Объём' => 'volume', 'Цена, ₽' => 'price'])->label('Цены')->addLabel('Добавить цену'),
                    ]),
                    Layout::section('Место на карте', 'Клик по карте, перетаскивание метки, координаты или поиск адреса.')->schema([
                        Map::make('form.location')->label('Точка самовывоза')->zoom(15),
                    ]),
                    Layout::section('Прочее')->schema([
                        Layout::grid([
                            Input::make('form.phone')->label('Телефон')->mask('+7 (999) 999-99-99'),
                            DateTimer::make('form.opensAt')->label('Открытие')->timeOnly(),
                        ]),
                        Utm::make('form.campaignLink')->label('Ссылка в рассылке'),
                        Label::make('form.code')->label('Код акции')->help('Label показывает значение, но не даёт его менять'),
                    ]),
                ],
                'Просмотр' => [
                    Layout::section('Карточка записи', 'Layout::legend() — значения только для чтения.')->schema([
                        Layout::legend(
                            ['id' => '#10482', 'client' => 'Ирина Кузнецова', 'total' => '4 280 ₽', 'created' => '5 ноября 2026, 11:42'],
                            [
                                Sight::make('id', 'Заказ')->mono()->copyable(),
                                Sight::make('client', 'Клиент'),
                                Sight::make('total', 'Сумма'),
                                Sight::make('created', 'Создан'),
                            ],
                        ),
                    ]),
                    Layout::section('Пустое состояние', 'EmptyState — когда данных ещё нет. Таблица принимает его через ->empty().')->schema([
                        EmptyState::make('Заказов пока нет')->icon('file')
                            ->description('Здесь появятся заказы из магазина. Можно создать первый вручную.')
                            ->actions([Link::make('Создать заказ')->icon('plus')->primary()->href('#')]),
                    ]),
                    Layout::section('Строки действий', 'Layout::actions() — «что делает» слева, кнопка справа.')->schema([
                        Layout::actions([
                            ActionRow::make('Очистить кеш')->description('Страницы соберутся заново при следующем открытии.')
                                ->buttons([Button::make('Очистить')->action($this->save(...))]),
                            ActionRow::make('Сбросить демо-данные')->description('Вернёт песочницу к исходному состоянию.')
                                ->buttons([Button::make('Сбросить')->danger()->confirm('Все изменения в песочнице пропадут.', 'Сбросить данные?', 'Сбросить')->action($this->save(...))]),
                        ]),
                    ]),
                ],
            ]),
        ];
    }

    #[Action]
    public function save(): Result
    {
        $tags = count($this->form->tags);

        return Result::toast("Форма в порядке: «{$this->form->title}», меток: {$tags}, скидка {$this->form->discount}%");
    }
}
