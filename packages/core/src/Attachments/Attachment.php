<?php

declare(strict_types=1);

namespace Vanda\Attachments;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * An uploaded file, stored in Orchid's `attachments` table:
 * the file lives on `disk` at `path` + `name` . `extension`.
 *
 * @property int $id
 * @property string $name
 * @property string $original_name
 * @property string $mime
 * @property ?string $extension
 * @property int $size
 * @property string $path
 * @property string $disk
 * @property ?int $user_id
 * @property ?string $group
 */
class Attachment extends Model
{
    protected $table = 'attachments';

    protected $fillable = ['name', 'original_name', 'mime', 'extension', 'size', 'sort', 'path', 'description', 'alt', 'hash', 'disk', 'user_id', 'group'];

    protected $casts = ['size' => 'integer', 'sort' => 'integer', 'user_id' => 'integer'];

    /**
     * Stores the file under a random name in a dated folder (or in $directory) and records it.
     * $extension is the one Upload::store() checked; without it the client's name decides.
     */
    public static function store(UploadedFile $file, string $disk, ?string $group = null, ?int $userId = null, ?string $directory = null, ?string $extension = null): self
    {
        $extension ??= strtolower($file->getClientOriginalExtension() ?: (string) $file->guessExtension());
        $path = $directory === null ? date('Y/m/d').'/' : trim($directory, '/').'/';
        $name = Str::random(40);

        Storage::disk($disk)->putFileAs($path, $file, $name.($extension === '' ? '' : '.'.$extension));

        return self::query()->create([
            'name' => $name,
            'original_name' => $file->getClientOriginalName(),
            'mime' => $file->getMimeType() ?? 'application/octet-stream',
            'extension' => $extension === '' ? null : $extension,
            'size' => $file->getSize(),
            'path' => $path,
            'hash' => sha1_file($file->getRealPath() ?: ''),
            'disk' => $disk,
            'user_id' => $userId,
            'group' => $group,
        ]);
    }

    public function relativePath(): string
    {
        return $this->path.$this->name.($this->extension ? '.'.$this->extension : '');
    }

    /**
     * Public URL of the file. Files served by this application get a root-relative URL,
     * so they open whatever host and port the panel runs on.
     */
    public function url(): ?string
    {
        $url = $this->storageUrl();
        if ($url === null) {
            return null;
        }

        $app = rtrim((string) config('app.url'), '/');

        return $app !== '' && str_starts_with($url, $app.'/') ? substr($url, strlen($app)) : $url;
    }

    /**
     * The URL exactly as the disk gives it (absolute for the public disk).
     */
    public function storageUrl(): ?string
    {
        $storage = Storage::disk($this->disk);

        return method_exists($storage, 'url') ? $storage->url($this->relativePath()) : null;
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime, 'image/');
    }

    /**
     * What the browser shows in an Upload field.
     *
     * @return array{id: int, name: string, size: int, mime: string, extension: ?string, url: ?string}
     */
    public function toUpload(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->original_name,
            'size' => $this->size,
            'mime' => $this->mime,
            'extension' => $this->extension,
            'url' => $this->url(),
        ];
    }

    protected static function booted(): void
    {
        static::deleted(function (self $attachment): void {
            Storage::disk($attachment->disk)->delete($attachment->relativePath());
            $attachment->getConnection()->table('attachmentable')->where('attachment_id', $attachment->id)->delete();
        });
    }
}
