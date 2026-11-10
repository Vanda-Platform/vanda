<?php

declare(strict_types=1);

namespace Vanda\Tests\Fixtures;

use DateTimeZone;
use Vanda\Actions\Button;
use Vanda\Attributes\Action;
use Vanda\Fields\DateTimer;
use Vanda\Fields\Input;
use Vanda\Fields\Label;
use Vanda\Fields\Map;
use Vanda\Fields\Matrix;
use Vanda\Fields\Option;
use Vanda\Fields\Relation;
use Vanda\Fields\Select;
use Vanda\Fields\TimeZone;
use Vanda\Fields\Utm;
use Vanda\Layouts\Layout;
use Vanda\Schema\Get;
use Vanda\Screen\Result;
use Vanda\Screen\Screen;

/**
 * The fields that came over from Orchid: Relation, Matrix, Map, UTM, Label, TimeZone.
 */
final class FieldsScreen extends Screen
{
    public ?int $owner = null;

    /** @var list<int> */
    public array $watchers = [];

    public string $domain = 'example.com';

    public ?int $colleague = null;

    public ?string $size = null;

    public ?string $roast = null;

    /** @var list<string> */
    public array $tags = [];

    /** @var list<array{volume?: string, price?: string}> */
    public array $prices = [['volume' => '250 g', 'price' => '890']];

    /** @var array{lat: float, lng: float}|null */
    public ?array $location = null;

    public ?string $link = null;

    public string $number = 'A-1042';

    public ?string $timezone = null;

    public ?string $phone = null;

    public ?string $opensAt = null;

    public function commandBar(): iterable
    {
        return [Button::make('Save')->primary()->action($this->save(...))];
    }

    public function layout(): iterable
    {
        return [
            Relation::make('owner')->label('Owner')->model(User::class, 'name')->searchColumns('name', 'email'),
            Relation::make('watchers')->model(User::class, 'name')->multiple()->display(fn (User $user) => "{$user->name} <{$user->email}>"),
            Input::make('domain'),
            Relation::make('colleague')->query(fn (Get $get) => User::query()->where('email', 'like', '%@'.$get('domain')))
                ->description(fn (User $user) => $user->email),
            Select::make('size')->options(['s' => 'Small', 'm' => 'Medium']),
            Select::make('roast')->searchable()->options(fn () => collect([
                Option::make('light', 'Light')->description('Fruity, for filter'),
                Option::make('dark', 'Dark')->description(''),
                ['value' => 'espresso', 'label' => 'Espresso', 'description' => 'For milk drinks', 'hint' => 'new'],
            ])),
            Select::make('tags')->multiple()->options(['new' => 'New'])->allowCustom(),
            Matrix::make('prices', ['Volume' => 'volume', 'Price' => 'price'])->addLabel('Add a price'),
            Map::make('location')->zoom(15)->center(59.93, 30.31),
            Utm::make('link'),
            Label::make('number')->label('Number'),
            TimeZone::make('timezone', DateTimeZone::EUROPE),
            Input::make('phone')->mask('+7 (999) 999-99-99')->suggestions(['+7 (800) 555-35-35']),
            DateTimer::make('opensAt')->timeOnly(),
            Layout::view('island', fn () => ['number' => $this->number, 'customer' => '<Ivan & Co>'])->id('island'),
        ];
    }

    #[Action]
    public function save(): Result
    {
        return Result::toast("Saved {$this->number}");
    }
}
