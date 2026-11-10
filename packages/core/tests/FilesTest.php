<?php

declare(strict_types=1);

use Symfony\Component\HttpKernel\Exception\HttpException;
use Vanda\Actions\Link;
use Vanda\Actions\ModalToggle;
use Vanda\Layouts\Files;
use Vanda\Layouts\Layout;
use Vanda\Panel;
use Vanda\Schema\SchemaCheck;
use Vanda\Schema\SchemaContext;
use Vanda\Screen\ScreenRuntime;
use Vanda\Tests\Fixtures\FilesScreen;
use Vanda\VandaException;

it('describes folders, files, the chosen file and the first layout', function () {
    $node = Panel::test(FilesScreen::class)->node('files');

    expect($node)->toMatchArray(['type' => 'files', 'layout' => 'list', 'selected' => '8', 'dependsOn' => ['q']])
        ->and($node['folders'])->toBe([
            ['name' => 'Certificates', 'href' => '/admin/files/3', 'meta' => '12 files'],
            ['name' => 'Old', 'href' => '/admin/files/4', 'hint' => 'in «Archive»'],
        ])
        ->and(array_map(fn (array $file) => array_diff_key($file, ['action' => true]), $node['files']))->toBe([
            ['key' => '7', 'name' => 'Price list.PDF', 'kind' => 'pdf', 'extension' => 'PDF', 'meta' => '51 KB · 12.03.2026'],
            ['key' => '8', 'name' => 'banner.jpg', 'thumb' => '/storage/banner.jpg', 'kind' => 'image', 'extension' => 'JPG', 'meta' => '3.4 MB'],
            ['key' => '9', 'name' => 'logo.svg', 'kind' => 'image', 'extension' => 'SVG', 'hint' => 'in «Brand»'],
            ['key' => '10', 'name' => 'secret.zip', 'kind' => 'archive', 'extension' => 'ZIP', 'meta' => '820 B'],
        ]);
});

it('signs the action of every file for that file and leaves files without one unclickable', function () {
    $files = collect(Panel::test(FilesScreen::class)->node('files')['files'])->keyBy('key');

    expect($files['7']['action'])->toMatchArray(['type' => 'modaltoggle', 'id' => 'files.7.action', 'modal' => 'file'])
        ->and($files['7']['action']['action'])->toMatchArray(['name' => 'loadFile', 'args' => ['id' => 7]])
        ->and($files['8']['action']['action']['args'])->toBe(['id' => 8])
        ->and($files['7']['action']['action']['token'])->not->toBe($files['8']['action']['action']['token'])
        // null, and a button the user may not press
        ->and($files['9'])->not->toHaveKey('action')
        ->and($files['10'])->not->toHaveKey('action');

    $runtime = ScreenRuntime::boot(FilesScreen::class, [], '/admin/files');
    $action = $files['8']['action'];
    [$status, $body] = $runtime->act('loadFile', $action['action']['args'], $action['action']['token'], [], $action['id']);

    expect($status)->toBe(200)->and($body['openModal'])->toBe('file')->and($body['page']['state']['fileName'])->toBe('file 8')
        ->and(fn () => $runtime->act('loadFile', ['id' => 7], $action['action']['token'], [], $action['id']))->toThrow(HttpException::class);
});

it('leaves out what an empty browser does not need', function () {
    expect(Panel::test(FilesScreen::class)->node('empty'))
        ->toMatchArray(['type' => 'files', 'id' => 'empty', 'layout' => 'grid', 'emptyText' => 'Nothing in the folder'])
        ->not->toHaveKeys(['folders', 'files', 'selected']);
});

it('says what a file action must return and checks the modal it opens', function () {
    $screen = new FilesScreen;
    $context = new SchemaContext($screen, []);

    expect(fn () => Layout::files()->id('f')->file('1', 'a.txt')->fileAction(fn () => 'open')->toSchema($context))
        ->toThrow(VandaException::class, 'The closure given to Layout::files()->fileAction() returned string.');

    $node = Layout::files()->id('f')->file('1', 'a.txt')->fileAction(fn () => ModalToggle::make('Open')->modal('fiel'))->toSchema($context);
    expect(SchemaCheck::problems($screen, ['layout' => [$node, ['type' => 'modal', 'id' => 'file']]])[0])
        ->toContain('opens the modal "fiel", but this screen has no Layout::modal(\'fiel\', …). Did you mean "file"?');

    expect(Layout::files()->id('f')->file('1', 'a.txt')->fileAction(fn (string $key) => Link::make('Open')->href('/files/'.$key))->toSchema($context)['files'][0]['action'])
        ->toMatchArray(['type' => 'link', 'href' => '/files/1']);
});

it('writes sizes for people in the language of the application', function () {
    expect([Files::size(null), Files::size(0), Files::size(820), Files::size(14_336), Files::size(3_565_158), Files::size(52_428_800)])
        ->toBe([null, '0 B', '820 B', '14 KB', '3.4 MB', '50 MB']);

    app()->setLocale('ru');
    expect(Files::size(3_565_158))->toBe('3,4 МБ')->and(Files::size(1_536))->toBe('1,5 КБ');
});

it('puts fields on the line of the view switch, where they stay fields of the screen', function () {
    $screen = Panel::test(FilesScreen::class);

    expect($screen->node('files')['children'])->toHaveCount(1)
        ->and($screen->node('files')['children'][0])->toMatchArray(['type' => 'input', 'path' => 'q', 'label' => 'Search', 'placeholder' => 'Folder or file name']);

    $screen->set('q', 'price')->assertSet('q', 'price');
});
