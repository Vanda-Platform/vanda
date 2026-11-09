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

    public string $content = '<p>Мы покупаем зелёное зерно у фермеров напрямую и обжариваем <strong>небольшими партиями</strong>.</p>';

    public string $notes = "## Доставка\nБесплатно от **3 000 ₽**.";

    public string $config = "{\n  \"currency\": \"RUB\",\n  \"roastDays\": [\"mon\", \"thu\"]\n}";

    /** @var list<int> */
    public array $categories = [11, 16];

    /** @var list<int> */
    public array $attachments = [];

    /** The cover's address, as a model column keeps it (Upload::target(UploadTarget::RelativeUrl)). */
    public ?string $cover = null;

    /** @var list<array{title?: string, image?: int}> */
    public array $slides = [['title' => 'Обжарка недели']];

    public ?int $manager = null;

    /** @var list<int> */
    public array $watchers = [];

    /** @var list<string> */
    public array $labels = ['кофе'];

    /** @var list<array{volume?: string, price?: string}> */
    public array $prices = [['volume' => '250 г', 'price' => '890'], ['volume' => '1 кг', 'price' => '2 990']];

    /** @var array{lat: float, lng: float}|null */
    public ?array $location = ['lat' => 55.7539, 'lng' => 37.6208];

    public ?string $campaignLink = 'https://kofe.example/sale?utm_source=newsletter';

    public string $code = 'PRM-2026-0042';

    public ?string $timezone = 'Europe/Moscow';

    public ?string $phone = null;

    public ?string $opensAt = '09:00';

    /** HTML written in TinyMCE, as Orchid kept it: classes, styles and data-* attributes stay. */
    public string $html = '<h2>Эспрессо-смесь «Бразилия»</h2><p class="lead">Плотное тело, ноты <span style="color: #b96ad9;">какао</span> и ореха. Подходит для <a href="/catalog/milk" data-product-id="7">напитков с молоком</a>.</p><table style="border-collapse: collapse; width: 100%;" border="1"><tbody><tr><td style="width: 50%;">Обжарка</td><td style="width: 50%;">Средняя</td></tr></tbody></table>';

    /** @var list<array{title?: string, text?: string}> */
    public array $blocks = [['title' => 'Доставка', 'text' => '<p>Бесплатно от <strong>3 000 ₽</strong>.</p>']];
}
