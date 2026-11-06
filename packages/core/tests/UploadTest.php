<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Vanda\Attachments\Attachment;
use Vanda\Tests\Fixtures\UploadScreen;
use Vanda\Tests\Fixtures\User;
use Vanda\Panel;

beforeEach(function () {
    Storage::fake('public');
    $this->user = User::query()->create(['name' => 'Ivan', 'email' => 'ivan@example.com']);
    $this->actingAs($this->user);
});

function upload(string $field, ?UploadedFile $file): Illuminate\Testing\TestResponse
{
    $page = test()->getJson('/admin/upload', ['X-Vanda' => 'true'])->json();

    return test()->post('/admin/upload', array_filter(['type' => 'upload', 'field' => $field, 'snapshot' => $page['snapshot'], 'file' => $file]), ['X-Vanda' => 'true', 'Accept' => 'application/json']);
}

it('stores an uploaded file as an attachment', function () {
    $response = upload('files', UploadedFile::fake()->create('price list.pdf', 120, 'application/pdf'))->assertOk();
    $attachment = Attachment::query()->findOrFail($response->json('id'));

    expect($response->json())->toMatchArray(['name' => 'price list.pdf', 'extension' => 'pdf', 'mime' => 'application/pdf'])
        ->and($attachment->group)->toBe('docs')
        ->and($attachment->user_id)->toBe($this->user->id);
    Storage::disk('public')->assertExists($attachment->relativePath());
});

it('checks size and type against the field', function () {
    upload('cover', UploadedFile::fake()->image('big.jpg')->size(2048))->assertStatus(422)->assertJsonStructure(['errors' => ['cover']]);
    upload('cover', UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'))->assertStatus(422);
    upload('cover', UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'))->assertStatus(422);
    upload('files', UploadedFile::fake()->createWithContent('shell.php', '<?php echo 1;'))->assertStatus(422);
    upload('cover', UploadedFile::fake()->image('ok.jpg'))->assertOk();

    expect(Attachment::query()->count())->toBe(1);
});

it('refuses uploads to fields that are not Upload fields of the screen', function () {
    upload('missing', UploadedFile::fake()->image('a.jpg'))->assertNotFound();
});

it('keeps only files that were already there or that this user uploaded', function () {
    $mine = upload('files', UploadedFile::fake()->create('a.txt', 1, 'text/plain'))->json('id');
    $foreign = Attachment::query()->create(['name' => 'x', 'original_name' => 'x.pdf', 'mime' => 'application/pdf', 'path' => '2026/', 'user_id' => 999])->id;

    Panel::test(UploadScreen::class)
        ->set('files', [$foreign, $mine, 'junk'])->assertSet('files', [$mine])
        ->set('cover', $foreign)->assertSet('cover', null);
});

it('describes the files already in the field', function () {
    $id = upload('files', UploadedFile::fake()->create('a.txt', 1, 'text/plain'))->json('id');

    $field = Panel::test(UploadScreen::class)->set('files', [$id])->call('save')->node('files');

    expect($field)->toMatchArray(['type' => 'upload', 'multiple' => true, 'accept' => '.pdf,.txt', 'maxFiles' => 2, 'maxSize' => 10485760])
        ->and($field['files'][0])->toMatchArray(['id' => $id, 'name' => 'a.txt', 'size' => 1024]);
});

it('keeps the URL of the file when the field targets URLs', function () {
    $response = upload('photo', UploadedFile::fake()->image('cover.jpg', 556, 322))->assertOk();
    $url = $response->json('value');
    $foreign = Attachment::query()->create(['name' => 'theirs', 'original_name' => 'x.jpg', 'mime' => 'image/jpeg', 'extension' => 'jpg', 'path' => 'photos/', 'disk' => 'public', 'user_id' => 999]);

    expect($url)->toStartWith('/storage/photos/')->toEndWith('.jpg')
        ->and(Attachment::query()->findOrFail($response->json('id'))->path)->toBe('photos/');

    Panel::test(UploadScreen::class)
        ->set('photo', '/storage/legacy/old-photo.jpg')->assertSet('photo', '/storage/legacy/old-photo.jpg')
        ->set('photo', $url)->assertSet('photo', $url)
        ->set('photo', '/storage/photos/theirs.jpg')->assertSet('photo', null)
        ->set('photo', '/etc/passwd')->assertSet('photo', null);

    expect($foreign->exists)->toBeTrue();
});

it('describes a stored URL even without an attachment behind it', function () {
    expect(Panel::test(UploadScreen::class)->node('photo')['files'])->toBe([[
        'name' => 'old-photo.jpg', 'mime' => 'image/jpeg', 'extension' => 'jpg', 'url' => '/storage/legacy/old-photo.jpg', 'value' => '/storage/legacy/old-photo.jpg',
    ]]);
});

it('sends the crops from the code to the browser', function () {
    expect(Panel::test(UploadScreen::class)->node('photo'))->toMatchArray([
        'crop' => [
            ['mode' => 'size', 'width' => 556, 'height' => 322, 'label' => 'Preview'],
            ['mode' => 'ratio', 'width' => 1, 'height' => 1, 'label' => 'Square', 'maxWidth' => 1200, 'minWidth' => 300, 'minHeight' => 300],
        ],
        'customCrop' => true,
    ])->and(Panel::test(UploadScreen::class)->node('cover'))->not->toHaveKey('crop');
});

it('uploads into a repeater row and keeps only own files there', function () {
    $mine = upload('slides.0.image', UploadedFile::fake()->image('slide.jpg'))->assertOk()->json('value');
    $foreign = Attachment::query()->create(['name' => 'x', 'original_name' => 'x.jpg', 'mime' => 'image/jpeg', 'path' => '2026/', 'user_id' => 999])->id;

    upload('slides.x.image', UploadedFile::fake()->image('slide.jpg'))->assertNotFound();
    upload('slides.0.title', UploadedFile::fake()->image('slide.jpg'))->assertNotFound();

    $screen = Panel::test(UploadScreen::class)
        ->set('slides', [['title' => 'One', 'image' => $mine], ['title' => 'Two', 'image' => $foreign]])
        ->assertSet('slides', [['title' => 'One', 'image' => $mine], ['title' => 'Two', 'image' => null]])
        // Rows swapped: the file moves with its row.
        ->set('slides', [['title' => 'Two'], ['title' => 'One', 'image' => $mine]])
        ->assertSet('slides', [['title' => 'Two'], ['title' => 'One', 'image' => $mine]])
        ->call('save');

    expect($screen->node('slides')['fields'][1]['files'][0])->toMatchArray(['id' => $mine, 'name' => 'slide.jpg', 'value' => $mine]);
});
