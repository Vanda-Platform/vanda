<?php

declare(strict_types=1);

namespace Vanda\Fields;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Vanda\Attachments\Attachment;
use Vanda\Schema\SchemaContext;

/**
 * File upload into Orchid's `attachments` table. The value is an attachment id,
 * or a list of ids with ->multiple():
 *
 *     Upload::make('form.cover')->label('Cover')->image()->maxSize(5),
 *     Upload::make('form.files')->multiple()->accept('.pdf,.xlsx')->maxFiles(10)->group('documents'),
 *
 *     // in the action
 *     $page->syncAttachments($this->form->files, 'documents');
 *
 * Files are sent to the screen's own URL, so its #[Can] and middleware apply,
 * and are checked against this field's rules on the server.
 */
final class Upload extends Field
{
    private bool $multiple = false;

    private ?string $accept = null;

    /** Megabytes. */
    private float $maxSize = 10;

    private ?int $maxFiles = null;

    private ?string $disk = null;

    private ?string $group = null;

    public function type(): string
    {
        return 'upload';
    }

    public function multiple(bool $multiple = true): static
    {
        $this->multiple = $multiple;

        return $this;
    }

    /**
     * Allowed types as in the HTML accept attribute: "image/*", ".pdf,.xlsx", "application/pdf".
     */
    public function accept(string $accept): static
    {
        $this->accept = $accept;

        return $this;
    }

    public function image(): static
    {
        return $this->accept('image/*');
    }

    public function maxSize(float $megabytes): static
    {
        $this->maxSize = $megabytes;

        return $this;
    }

    public function maxFiles(int $count): static
    {
        $this->maxFiles = $count;

        return $this;
    }

    /**
     * Filesystem disk; config('vanda.attachments.disk') by default.
     */
    public function disk(string $disk): static
    {
        $this->disk = $disk;

        return $this;
    }

    /**
     * Stored in the attachment's `group` column, to keep several upload fields of one model apart.
     */
    public function group(string $group): static
    {
        $this->group = $group;

        return $this;
    }

    /** Never stored: a web server could run them, or they rewrite its rules. */
    private const FORBIDDEN = ['php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'phar', 'pht', 'phps', 'cgi', 'pl', 'asp', 'aspx', 'jsp', 'htaccess', 'htpasswd'];

    /**
     * Checks the file against this field and stores it.
     *
     * @throws ValidationException
     */
    public function store(UploadedFile $file): Attachment
    {
        Validator::make(['file' => $file], ['file' => ['required', 'file', 'max:'.(int) round($this->maxSize * 1024)]])->validate();

        $extension = strtolower($file->getClientOriginalExtension());
        if (in_array($extension, self::FORBIDDEN, true) || str_starts_with(strtolower($file->getClientOriginalName()), '.ht')) {
            throw ValidationException::withMessages(['file' => [__('vanda::upload.forbidden')]]);
        }

        // Several accepted types mean "any of them".
        $types = array_filter(array_map('trim', explode(',', (string) $this->accept)));
        if ($types !== [] && array_filter($types, fn (string $type) => $this->matchesType($file, $type)) === []) {
            throw ValidationException::withMessages(['file' => [__('vanda::upload.type', ['types' => $this->accept])]]);
        }

        return Attachment::store($file, $this->disk ?? (string) config('vanda.attachments.disk', 'public'), $this->group, Auth::id() === null ? null : (int) Auth::id());
    }

    /**
     * Keeps ids that were already in the field and files this user has just uploaded,
     * so nobody can attach someone else's file by typing its id.
     *
     * @return int|list<int>|null
     */
    public function sanitize(mixed $value, mixed $current = null): int|array|null
    {
        $ids = array_values(array_unique(array_map('intval', array_filter((array) $value, 'is_numeric'))));
        $known = array_map('intval', (array) $current);
        $fresh = array_diff($ids, $known);

        if ($fresh !== []) {
            $own = Attachment::query()->whereKey($fresh)->where('user_id', Auth::id())->pluck('id')->map('intval')->all();
            $ids = array_values(array_filter($ids, static fn (int $id) => in_array($id, $known, true) || in_array($id, $own, true)));
        }

        if ($this->maxFiles !== null) {
            $ids = array_slice($ids, 0, $this->maxFiles);
        }

        return $this->multiple ? $ids : ($ids[0] ?? null);
    }

    protected function fieldProperties(SchemaContext $context): array
    {
        $ids = array_map('intval', array_filter((array) data_get($context->state, $this->path()), 'is_numeric'));
        $files = $ids === [] ? [] : Attachment::query()->whereKey($ids)->get()->sortBy(static fn (Attachment $file) => array_search($file->id, $ids, true))->values();

        return [
            'multiple' => $this->multiple,
            'accept' => $this->accept,
            'maxSize' => $this->maxSize * 1024 * 1024,
            'maxFiles' => $this->maxFiles,
            'files' => array_map(static fn (Attachment $file) => $file->toUpload(), is_array($files) ? $files : $files->all()),
        ];
    }

    /**
     * "image/*" does not let SVG in: an SVG opened from the same site can run scripts.
     * Accept it explicitly with ".svg" or "image/svg+xml".
     */
    private function matchesType(UploadedFile $file, string $type): bool
    {
        $mime = strtolower((string) $file->getMimeType());
        $type = strtolower($type);

        return match (true) {
            str_starts_with($type, '.') => strtolower($file->getClientOriginalExtension()) === substr($type, 1),
            str_ends_with($type, '/*') => str_starts_with($mime, substr($type, 0, -1)) && $mime !== 'image/svg+xml',
            default => $mime === $type,
        };
    }
}
