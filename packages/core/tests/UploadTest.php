<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Vanda\Attachments\Attachment;
use Vanda\Tests\Fixtures\UploadScreen;
use Vanda\Tests\Fixtures\User;
use Vanda\Vanda;

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

    Vanda::test(UploadScreen::class)
        ->set('files', [$foreign, $mine, 'junk'])->assertSet('files', [$mine])
        ->set('cover', $foreign)->assertSet('cover', null);
});

it('describes the files already in the field', function () {
    $id = upload('files', UploadedFile::fake()->create('a.txt', 1, 'text/plain'))->json('id');

    $field = Vanda::test(UploadScreen::class)->set('files', [$id])->call('save')->node('files');

    expect($field)->toMatchArray(['type' => 'upload', 'multiple' => true, 'accept' => '.pdf,.txt', 'maxFiles' => 2, 'maxSize' => 10485760])
        ->and($field['files'][0])->toMatchArray(['id' => $id, 'name' => 'a.txt', 'size' => 1024]);
});
