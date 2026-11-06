<?php

declare(strict_types=1);

namespace Orchid\Attachment;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Orchid\Attachment\Models\Attachment;

/**
 * Orchid's File: stores an uploaded file and records it in `attachments`:
 *
 *     $attachment = (new File($upload, 'public', 'bills'))->allowDuplicates()->load();
 *
 * Without allowDuplicates() a file with the same hash on the same disk is reused.
 */
class File
{
    private bool $duplicates = false;

    private ?string $path = null;

    public function __construct(
        private readonly UploadedFile $file,
        private readonly ?string $disk = null,
        private readonly ?string $group = null,
    ) {}

    /**
     * Folder on the disk instead of the dated one.
     */
    public function path(string $path): static
    {
        $this->path = $path;

        return $this;
    }

    public function allowDuplicates(): static
    {
        $this->duplicates = true;

        return $this;
    }

    public function load(): Attachment
    {
        $disk = $this->disk ?? (string) config('vanda.attachments.disk', 'public');

        if (! $this->duplicates) {
            $existing = Attachment::query()->where('hash', sha1_file($this->file->getRealPath() ?: ''))->where('disk', $disk)->first();
            if ($existing !== null) {
                return $existing;
            }
        }

        $stored = Attachment::store($this->file, $disk, $this->group, Auth::id() === null ? null : (int) Auth::id(), $this->path);

        return Attachment::query()->findOrFail($stored->getKey());
    }
}
