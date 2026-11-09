<?php

declare(strict_types=1);

namespace Vanda\Tests\Fixtures;

use Vanda\Attributes\Action;
use Vanda\Fields\Input;
use Vanda\Fields\Repeater;
use Vanda\Fields\TinyMce;
use Vanda\Layouts\Layout;
use Vanda\Screen\Result;
use Vanda\Screen\Screen;

final class TinyMceScreen extends Screen
{
    public string $body = '<p class="lead">Old <a href="/catalog/1" data-product-id="1">markup</a></p>';

    public string $note = '';

    /** @var list<array{title?: string, text?: string}> */
    public array $blocks = [];

    public string $quick = '';

    public function layout(): iterable
    {
        return [
            TinyMce::make('body')->label('Text')->required()->uploads(1)->linkList(fn () => [
                ['title' => 'Catalog', 'value' => '/catalog'],
                ['title' => 'Products', 'menu' => [['title' => 'Composite', 'value' => '/catalog/1']]],
            ]),
            TinyMce::make('note')->height(240)->plugins(['link', 'lists'])->toolbar('bold italic | link')->menubar(false)->embeds([]),
            Repeater::make('blocks')->fields([Input::make('title'), TinyMce::make('text')->uploads(1)]),
            Layout::modal('quickEdit', 'Quick edit')->schema([TinyMce::make('quick')->toolbar(['bold', 'link'])->menubar('edit insert')]),
        ];
    }

    #[Action]
    public function save(): Result
    {
        return Result::toast('Saved');
    }
}
