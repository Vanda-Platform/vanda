<?php

namespace App\Vanda\Screens;

use App\Vanda\Forms\ShowcaseForm;
use Vanda\Actions\Button;
use Vanda\Attributes\Action;
use Vanda\Fields\CheckBox;
use Vanda\Fields\Color;
use Vanda\Fields\DateRange;
use Vanda\Fields\DateTimer;
use Vanda\Fields\Input;
use Vanda\Fields\KeyValue;
use Vanda\Fields\NumberRange;
use Vanda\Fields\Password;
use Vanda\Fields\Radio;
use Vanda\Fields\RadioButtons;
use Vanda\Fields\Range;
use Vanda\Fields\Repeater;
use Vanda\Fields\Select;
use Vanda\Fields\Switcher;
use Vanda\Fields\TextArea;
use Vanda\Layouts\Alert;
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
