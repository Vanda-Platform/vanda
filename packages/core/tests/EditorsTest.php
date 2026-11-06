<?php

declare(strict_types=1);

use Vanda\Navigation\SearchResult;
use Vanda\Support\Html;
use Vanda\Tests\Fixtures\EditorsScreen;
use Vanda\Tests\Fixtures\SecuredScreen;
use Vanda\Tests\Fixtures\User;
use Vanda\Panel;

it('keeps only editor markup in rich text', function (string $dirty, string $clean) {
    expect(Html::clean($dirty))->toBe($clean);
})->with([
    'formatting stays' => ['<h2>Title</h2><p><strong>Bold</strong> and <em>italic</em></p><ul><li><p>One</p></li></ul>', '<h2>Title</h2><p><strong>Bold</strong> and <em>italic</em></p><ul><li><p>One</p></li></ul>'],
    'scripts and handlers go' => ['<p onclick="x()">Hi<script>alert(1)</script></p><img src="x.png" onerror="alert(1)">', '<p>Hi</p><img src="x.png">'],
    'unsafe links lose href' => ['<a href="javascript:alert(1)">x</a><a href=" JaVa script:1">y</a><a href="/about" target="_blank">ok</a>', '<a>x</a><a>y</a><a href="/about" target="_blank" rel="noopener noreferrer">ok</a>'],
    'wrappers unwrap' => ['<div><span style="color:red">Кофе</span></div>', 'Кофе'],
    'empty' => ['  ', ''],
    'tables and alignment stay' => [
        '<table><tbody><tr><th colspan="2" style="width: 120px; color: red">A</th></tr><tr><td>1</td><td>2</td></tr></tbody></table><p style="text-align: center; font-size: 40px">Mid</p>',
        '<table><tbody><tr><th colspan="2" style="width: 120px">A</th></tr><tr><td>1</td><td>2</td></tr></tbody></table><p style="text-align: center">Mid</p>',
    ],
    'unsafe style goes' => ['<p style="background: url(javascript:x)">x</p>', '<p>x</p>'],
    'quill alignment becomes style' => ['<p class="ql-align-center ql-indent-1">Mid</p><h2 class="ql-align-right">R</h2>', '<p style="text-align: center">Mid</p><h2 style="text-align: right">R</h2>'],
    'iframes go by default' => ['<p>Video</p><iframe src="https://rutube.ru/play/embed/1"></iframe>', '<p>Video</p>'],
]);

it('keeps iframes only from the allowed hosts over https', function () {
    $html = '<iframe src="https://rutube.ru/play/embed/1" allowfullscreen onload="x()"></iframe>'
        .'<iframe src="http://rutube.ru/play/embed/2"></iframe><iframe src="https://rutube.ru.evil.com/x"></iframe>';

    expect(Html::clean($html, embedHosts: ['rutube.ru']))
        ->toBe('<iframe src="https://rutube.ru/play/embed/1" allowfullscreen></iframe>');
});

it('saves rich text the user did not change as it was', function () {
    Panel::test(EditorsScreen::class)
        ->set('article', '<p><span style="color: red">Old</span> markup</p>')
        ->assertSet('article', '<p><span style="color: red">Old</span> markup</p>')
        ->set('article', '<p><span>New</span></p><iframe src="https://rutube.ru/play/embed/1"></iframe>')
        ->assertSet('article', '<p>New</p><iframe src="https://rutube.ru/play/embed/1"></iframe>');
});

it('cleans rich text from the browser before it reaches the state', function () {
    Panel::test(EditorsScreen::class)
        ->set('content', '<p>Hi<script>alert(1)</script></p>')
        ->assertSet('content', '<p>Hi</p>');
});

it('describes editors and charts in the IR', function () {
    $screen = Panel::test(EditorsScreen::class);

    expect($screen->node('content'))->toMatchArray(['type' => 'richtext', 'minHeight' => 120])
        ->and($screen->node('article'))->toMatchArray(['embeds' => ['rutube.ru'], 'htmlMode' => true])
        ->and($screen->node('article')['tags'])->toContain('table', 'img', 'h4')
        ->and($screen->node('notes'))->toMatchArray(['type' => 'markdown', 'minHeight' => 160])
        ->and($screen->node('settings'))->toMatchArray(['type' => 'code', 'language' => 'json', 'minHeight' => 160])
        ->and($screen->node('settings'))->not->toHaveKey('lineNumbers')
        ->and($screen->node('layout.4'))->toMatchArray([
            'type' => 'chart', 'kind' => 'line', 'title' => 'Revenue', 'labels' => ['Mon', 'Tue'],
            'series' => [['name' => 'This week', 'values' => [1, 2.5]]], 'unit' => '₽', 'height' => 220, 'span' => 2,
        ]);
});

it('draws a menu only with the items the user may use', function () {
    $page = Panel::test(EditorsScreen::class)->page();

    expect($page['commandBar'])->toHaveCount(1)
        ->and($page['commandBar'][0])->toMatchArray(['type' => 'dropdown', 'label' => 'More'])
        ->and(array_column($page['commandBar'][0]['children'], 'label'))->toBe(['Docs']);
});

it('finds records for the palette and hides those behind forbidden screens', function () {
    $user = User::query()->create(['name' => 'Ivan', 'email' => 'ivan@example.com']);
    Panel::search(fn (string $query) => [
        SearchResult::make("Open {$query}")->href('/somewhere')->avatar('Ivan Petrov'),
        SearchResult::make('Secured')->to(SecuredScreen::class),
    ]);

    $this->actingAs($user);
    expect($this->getJson('/panel/vanda/search?q=ab')->json('results'))->toBe([
        ['label' => 'Open ab', 'href' => '/somewhere', 'initials' => 'IP', 'hue' => crc32('Ivan Petrov') % 6],
    ])->and($this->getJson('/panel/vanda/search?q=a')->json('results'))->toBe([])
        ->and($this->getJson('/admin/showcase', ['X-Vanda' => 'true'])->json('app.search'))->toEndWith('/panel/vanda/search');
});
