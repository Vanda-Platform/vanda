<?php

namespace App\Enums;

enum Country: string
{
    case Russia = 'RU';
    case Germany = 'DE';
    case France = 'FR';
    case Kazakhstan = 'KZ';

    public function label(): string
    {
        return match ($this) {
            self::Russia => 'Россия',
            self::Germany => 'Германия',
            self::France => 'Франция',
            self::Kazakhstan => 'Казахстан',
        };
    }

    public function inEu(): bool
    {
        return in_array($this, [self::Germany, self::France], true);
    }

    /**
     * @return array<int, string> city id => name
     */
    public function cities(): array
    {
        return match ($this) {
            self::Russia => [1 => 'Москва', 2 => 'Санкт-Петербург', 3 => 'Казань'],
            self::Germany => [10 => 'Берлин', 11 => 'Мюнхен', 12 => 'Гамбург'],
            self::France => [20 => 'Париж', 21 => 'Лион'],
            self::Kazakhstan => [30 => 'Алматы', 31 => 'Астана'],
        };
    }
}
