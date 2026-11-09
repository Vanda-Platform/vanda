<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Vanda\Attachments\Attachment;
use Vanda\Fields\TinyMce;
use Vanda\Panel;
use Vanda\Schema\SchemaCheck;
use Vanda\Schema\SchemaContext;
use Vanda\Support\Html;
use Vanda\Tests\Fixtures\TinyMceScreen;
use Vanda\Tests\Fixtures\User;
use Vanda\VandaException;

it('describes the editor with the old project\'s plugins and toolbar by default', function () {
    $screen = Panel::test(TinyMceScreen::class);

    expect($screen->node('body'))->toMatchArray([
        'type' => 'tinymce',
        'label' => 'Text',
        'height' => 400,
        'plugins' => TinyMce::PLUGINS,
        'toolbar' => TinyMce::TOOLBAR,
        'uploads' => 1048576,
        'linkList' => [
            ['title' => 'Catalog', 'value' => '/catalog'],
            ['title' => 'Products', 'menu' => [['title' => 'Composite', 'value' => '/catalog/1']]],
        ],
    ])->and($screen->node('body'))->not->toHaveKey('menubar')
        ->and(TinyMce::PLUGINS)->toBe(explode(' ', 'preview searchreplace autolink autosave code visualblocks visualchars fullscreen image link media codesample table charmap pagebreak nonbreaking anchor insertdatetime advlist lists wordcount help quickbars emoticons'))
        ->and(TinyMce::TOOLBAR)->toBe('undo redo | blocks fontsize | bold italic underline strikethrough | forecolor backcolor | alignleft aligncenter alignright alignjustify | numlist bullist outdent indent | link image media table | charmap emoticons | removeformat | code fullscreen preview');

    expect($screen->node('note'))->toMatchArray(['height' => 240, 'plugins' => ['link', 'lists'], 'toolbar' => 'bold italic | link', 'menubar' => ''])
        ->and($screen->node('note'))->not->toHaveKeys(['uploads', 'linkList'])
        ->and($screen->node('quick'))->toMatchArray(['toolbar' => ['bold', 'link'], 'menubar' => 'edit insert'])
        ->and($screen->node('blocks')['fields'][1])->toMatchArray(['type' => 'tinymce', 'path' => 'text', 'uploads' => 1048576]);
});

it('bundles every plugin of the default list', function () {
    expect(array_diff(TinyMce::PLUGINS, TinyMce::bundledPlugins()))->toBe([]);
});

it('says how a link list must look', function () {
    $field = TinyMce::make('body')->linkList([['title' => 'Catalog']]);

    expect(fn () => $field->toSchema(new SchemaContext(new TinyMceScreen, [])))
        ->toThrow(VandaException::class, 'TinyMce::make(\'body\')->linkList(): item "Catalog" has no "value" (the address) and no "menu"');
});

it('checks the plugins against those the renderer bundles', function () {
    $screen = new TinyMceScreen;
    $node = TinyMce::make('body')->plugins(['link', 'tabel'])->id('body')->toSchema(new SchemaContext($screen, ['body' => '']));

    expect(SchemaCheck::problems($screen, ['layout' => [$node]])[0])
        ->toContain('tinymce at ')
        ->toContain('has the TinyMCE plugin "tabel", which the renderer does not bundle, so the editor would try to download it and fail. Did you mean "table"? Bundled plugins: accordion, advlist,');
});

it('keeps the markup TinyMCE writes and drops what runs code', function (string $dirty, string $clean) {
    expect(Html::cleanTinyMce($dirty, TinyMce::VIDEO_HOSTS))->toBe($clean);
})->with([
    'classes, ids and data attributes stay' => [
        '<p class="lead text-center" id="intro" data-block="1"><a href="/catalog/x" data-product-id="42" class="product-link" title="X" target="_blank">X</a></p>',
        '<p class="lead text-center" id="intro" data-block="1"><a href="/catalog/x" data-product-id="42" class="product-link" title="X" target="_blank" rel="noopener">X</a></p>',
    ],
    'any harmless CSS stays' => [
        '<p style="font-size: 14pt; font-family: \'Times New Roman\', serif; line-height: 1.5; margin: 0 0 10px; text-indent: 2em;">a</p><table style="border-collapse: collapse; width: 33.3333%; height: 22.4px;" border="1"><colgroup><col style="width: 50.0943%;"></colgroup><tbody><tr><td style="width: 50.0943%; border-color: #ced4da;" valign="top">1</td></tr></tbody></table>',
        '<p style="font-size: 14pt; font-family: \'Times New Roman\', serif; line-height: 1.5; margin: 0 0 10px; text-indent: 2em">a</p><table style="border-collapse: collapse; width: 33.3333%; height: 22.4px" border="1"><colgroup><col style="width: 50.0943%"></colgroup><tbody><tr><td style="width: 50.0943%; border-color: #ced4da" valign="top">1</td></tr></tbody></table>',
    ],
    'CSS that loads or pins goes' => [
        '<div style="background: url(https://evil.example/x.png); position: fixed; color: red; behavior: url(x.htc); width: expression(alert(1)); top: 0">x</div><p style="position: relative">y</p>',
        '<div style="color: red; top: 0">x</div><p style="position: relative">y</p>',
    ],
    'scripts, handlers and unsafe links go' => [
        '<p onclick="x()">Hi<script>alert(1)</script></p><a href="javascript:alert(1)">a</a><img src="x.png" onerror="alert(1)" data-mce-src="javascript:1"><style>p{}</style><form><input></form>',
        '<p>Hi</p><a>a</a><img src="x.png">',
    ],
    'video from known hosts stays, other frames go' => [
        '<p><iframe src="https://www.youtube.com/embed/abc" width="560" height="314" allowfullscreen="allowfullscreen"></iframe></p><iframe src="//rutube.ru/play/embed/1"></iframe><iframe src="https://evil.example/x"></iframe><iframe srcdoc="<script>x</script>" src="https://vk.com/video_ext.php"></iframe>',
        '<p><iframe src="https://www.youtube.com/embed/abc" width="560" height="314" allowfullscreen="allowfullscreen"></iframe></p><iframe src="//rutube.ru/play/embed/1"></iframe><iframe src="https://vk.com/video_ext.php"></iframe>',
    ],
    'video files, pictures and the accordion stay' => [
        '<video controls="controls" width="300" poster="/p.jpg"><source src="/v.mp4" type="video/mp4"></video><figure class="image"><img src="/a.jpg" alt="a" srcset="/a.jpg 1x, /a@2x.jpg 2x"><figcaption>c</figcaption></figure><details open><summary>Q</summary><p>A</p></details>',
        '<video controls="controls" width="300" poster="/p.jpg"><source src="/v.mp4" type="video/mp4"></video><figure class="image"><img src="/a.jpg" alt="a" srcset="/a.jpg 1x, /a@2x.jpg 2x"><figcaption>c</figcaption></figure><details open><summary>Q</summary><p>A</p></details>',
    ],
    'the page break stays, other comments go' => ['<p>a</p><!-- pagebreak --><p>b</p><!-- note -->', '<p>a</p><!-- pagebreak --><p>b</p>'],
    'raster data pictures stay, SVG goes' => [
        '<img src="data:image/png;base64,iVBORw0KGgo=" alt="p"><img src="data:image/svg+xml;base64,PHN2Zz4=" alt="s">',
        '<img src="data:image/png;base64,iVBORw0KGgo=" alt="p"><img alt="s">',
    ],
    'odd classes and ids go, bare spans unwrap' => [
        '<span class="a&quot; b">x</span><span>y</span><a id="1 x" name="top">z</a>',
        '<span class="b">x</span>y<a name="top">z</a>',
    ],
    'empty' => [' ', ''],
]);

it('keeps no frames when no host is allowed', function () {
    expect(Html::cleanTinyMce('<iframe src="https://www.youtube.com/embed/abc"></iframe><p>a</p>'))->toBe('<p>a</p>');
});

it('saves text the user did not change as it was and cleans the rest', function () {
    $old = '<p class="lead">Old <a href="/catalog/1" data-product-id="1">markup</a></p>';

    Panel::test(TinyMceScreen::class)
        ->set('body', $old)
        ->assertSet('body', $old)
        ->set('body', '<p class="lead" onclick="x()">New<script>1</script></p>')
        ->assertSet('body', '<p class="lead">New</p>')
        ->set('note', '<iframe src="https://www.youtube.com/embed/abc"></iframe><p>b</p>')
        ->assertSet('note', '<p>b</p>')
        ->set('blocks', [['title' => 'One', 'text' => '<p style="color: red; position: fixed">r</p>']])
        ->assertSet('blocks', [['title' => 'One', 'text' => '<p style="color: red">r</p>']]);
});

it('validates the editor like any field', function () {
    Panel::test(TinyMceScreen::class)
        ->set('body', '')
        ->call('save')
        ->assertHasErrors(['body'])
        ->set('body', '<p>Text</p>')
        ->call('save')
        ->assertHasNoErrors()
        ->assertToast('Saved');
});

it('uploads images into the editor, inside a repeater row too', function () {
    Storage::fake('public');
    $this->actingAs(User::query()->create(['name' => 'Ivan', 'email' => 'ivan@example.com']));
    $page = $this->getJson('/admin/tinymce', ['X-Vanda' => 'true'])->json();
    $send = fn (string $field, UploadedFile $file) => $this->post('/admin/tinymce', ['type' => 'upload', 'field' => $field, 'snapshot' => $page['snapshot'], 'file' => $file], ['X-Vanda' => 'true', 'Accept' => 'application/json']);

    $response = $send('body', UploadedFile::fake()->image('chart.png'))->assertOk();
    expect($response->json('value'))->toStartWith('/storage/')->toEndWith('.png')
        ->and(Attachment::query()->findOrFail($response->json('id'))->group)->toBe('richtext');

    $send('body', UploadedFile::fake()->image('huge.jpg')->size(2048))->assertStatus(422);
    $send('body', UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'))->assertStatus(422);
    $send('note', UploadedFile::fake()->image('chart.png'))->assertNotFound();
    expect($send('blocks.0.text', UploadedFile::fake()->image('row.jpg'))->assertOk()->json('value'))->toEndWith('.jpg');
});
