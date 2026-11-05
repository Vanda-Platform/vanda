<?php

namespace App\Vanda\Forms;

use Vanda\Attributes\Secret;
use Vanda\Attributes\Validate;
use Vanda\Form\Form;

final class ShowcaseForm extends Form
{
    #[Validate('required|string|max:120')]
    public string $title = 'Летняя распродажа 2026';

    public string $slug = '';

    #[Validate('nullable|numeric|min:0')]
    public ?string $price = '12500';

    #[Secret]
    public string $password = '';

    public string $description = 'Свежая обжарка каждую неделю. Цветочный аромат, ноты бергамота и чёрного чая.';

    /** @var list<string> */
    public array $tags = ['new', 'hit'];

    public string $category = 'beans';

    public bool $featured = true;

    public bool $published = true;

    public string $delivery = 'courier';

    public string $state = 'review';

    public ?string $startsAt = '2026-03-12T14:30';

    /** @var array{start?: string, end?: string}|null */
    public ?array $period = ['start' => '2026-03-01', 'end' => '2026-03-31'];

    public int $discount = 15;

    /** @var array{from?: int|null, to?: int|null} */
    public array $priceRange = ['from' => 500, 'to' => 3500];

    public string $color = '#12805C';

    /** @var list<array{name?: string, value?: string}> */
    public array $specs = [['name' => 'Вес', 'value' => '1 кг'], ['name' => 'Обжарка', 'value' => 'Средняя']];

    /** @var list<array{key: string, value: string}> */
    public array $utm = [['key' => 'utm_source', 'value' => 'newsletter'], ['key' => 'utm_medium', 'value' => 'email']];
}
