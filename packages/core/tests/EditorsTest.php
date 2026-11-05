<?php

declare(strict_types=1);

use Vanda\Navigation\SearchResult;
use Vanda\Support\Html;
use Vanda\Tests\Fixtures\EditorsScreen;
use Vanda\Tests\Fixtures\SecuredScreen;
use Vanda\Tests\Fixtures\User;
use Vanda\Vanda;

it('keeps only editor markup in rich text', function (string $dirty, string $clean) {
    expect(Html::clean($dirty))->toBe($clean);
})->with([
    'formatting stays' => ['<h2>Title</h2><p><strong>Bold</strong> and <em>italic</em></p><ul><li><p>One</p></li></ul>', '<h2>Title</h2><p><strong>Bold</strong> and <em>italic</em></p><ul><li><p>One</p></li></ul>'],
    'scripts and handlers go' => ['<p onclick="x()">Hi<script>alert(1)</script></p><img src="x.png" onerror="alert(1)">', '<p>Hi</p><img src="x.png">'],
    'unsafe links lose href' => ['<a href="javascript:alert(1)">x</a><a href=" JaVa script:1">y</a><a href="/about" target="_blank">ok</a>', '<a>x</a><a>y</a><a href="/about" target="_blank" rel="noopener noreferrer">ok</a>'],
    'wrappers unwrap' => ['<div><span style="color:red">Кофе</span></div>', 'Кофе'],
    'empty' => ['  ', ''],
]);

it('cleans rich text from the browser before it reaches the state', function () {
    Vanda::test(EditorsScreen::class)
        ->set('content', '<p>Hi<script>alert(1)</script></p>')
        ->assertSet('content', '<p>Hi</p>');
});

it('describes editors and charts in the IR', function () {
    $screen = Vanda::test(EditorsScreen::class);

    expect($screen->node('content'))->toMatchArray(['type' => 'richtext', 'minHeight' => 120])
        ->and($screen->node('notes'))->toMatchArray(['type' => 'markdown', 'minHeight' => 160])
        ->and($screen->node('settings'))->toMatchArray(['type' => 'code', 'language' => 'json', 'minHeight' => 160])
        ->and($screen->node('settings'))->not->toHaveKey('lineNumbers')
        ->and($screen->node('layout.3'))->toMatchArray([
            'type' => 'chart', 'kind' => 'line', 'title' => 'Revenue', 'labels' => ['Mon', 'Tue'],
            'series' => [['name' => 'This week', 'values' => [1, 2.5]]], 'unit' => '₽', 'height' => 220, 'span' => 2,
        ]);
});

it('draws a menu only with the items the user may use', function () {
    $page = Vanda::test(EditorsScreen::class)->page();

    expect($page['commandBar'])->toHaveCount(1)
        ->and($page['commandBar'][0])->toMatchArray(['type' => 'dropdown', 'label' => 'More'])
        ->and(array_column($page['commandBar'][0]['children'], 'label'))->toBe(['Docs']);
});

it('finds records for the palette and hides those behind forbidden screens', function () {
    $user = User::query()->create(['name' => 'Ivan', 'email' => 'ivan@example.com']);
    Vanda::search(fn (string $query) => [
        SearchResult::make("Open {$query}")->href('/somewhere')->avatar('Ivan Petrov'),
        SearchResult::make('Secured')->to(SecuredScreen::class),
    ]);

    $this->actingAs($user);
    expect($this->getJson('/panel/vanda/search?q=ab')->json('results'))->toBe([
        ['label' => 'Open ab', 'href' => '/somewhere', 'initials' => 'IP', 'hue' => crc32('Ivan Petrov') % 6],
    ])->and($this->getJson('/panel/vanda/search?q=a')->json('results'))->toBe([])
        ->and($this->getJson('/admin/showcase', ['X-Vanda' => 'true'])->json('app.search'))->toEndWith('/panel/vanda/search');
});
