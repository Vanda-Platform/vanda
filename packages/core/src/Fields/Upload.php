<?php

declare(strict_types=1);

namespace Vanda\Fields;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Vanda\Attachments\Attachment;
use Vanda\Layouts\Avatar;
use Symfony\Component\Mime\MimeTypes;
use Vanda\Schema\SchemaContext;
use Vanda\VandaException;

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
 *
 * A picture kept as a URL in the model's own column, cropped in the browser first
 * (Orchid's Cropper with targetRelativeUrl()):
 *
 *     Upload::make('form.photo')->image()
 *         ->target(UploadTarget::RelativeUrl)          // "/storage/2026/01/15/abc.jpg"
 *         ->crop(Crop::size(556, 322)->label('Preview'), Crop::ratio(1, 1)->label('Square'))
 *         ->customCrop(),                               // the user may pick other proportions
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

    private ?string $avatar = null;

    private UploadTarget $target = UploadTarget::Id;

    /** @var list<Crop> */
    private array $crops = [];

    private bool $customCrop = false;

    private ?string $directory = null;

    /** WebP quality (1–100) set with ->webp(), false after ->keepFormat(), null: the default (see webpQuality()). */
    private int|false|null $webp = null;

    /** The quality images are re-encoded at by default: hardly visible, and several times lighter than a camera's JPEG. */
    public const WEBP_QUALITY = 85;

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
     *
     * HTML, SVG, XML and JavaScript files are refused unless named here by extension or exact type
     * (".svg", "image/svg+xml"): served from the panel's own domain, they run scripts as its users.
     * Accept them only on a disk served from another domain.
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
     * A round photo with "Upload photo" and "Remove" buttons, for a person's picture.
     * Until a photo is uploaded, the initials of this name are shown.
     */
    public function avatar(string $name): static
    {
        $this->avatar = $name;
        $this->multiple = false;

        return $this->image();
    }

    /**
     * Stored in the attachment's `group` column, to keep several upload fields of one model apart.
     */
    public function group(string $group): static
    {
        $this->group = $group;

        return $this;
    }

    /**
     * What the field keeps: the attachment id (default) or the file's URL, for a model
     * that stores the picture's address in its own column.
     */
    public function target(UploadTarget $target): static
    {
        $this->target = $target;

        return $this;
    }

    /**
     * Images are cropped in the browser before they are uploaded; the user picks one of these.
     * An image already in the field can be cropped again.
     */
    public function crop(Crop ...$crops): static
    {
        $this->crops = array_values($crops);

        return $this;
    }

    /**
     * Besides the crops from the code, the user may crop freely, type their own proportions
     * or size (remembered in their browser), or upload the image as it is.
     */
    public function customCrop(bool $allowed = true): static
    {
        $this->customCrop = $allowed;

        return $this;
    }

    /**
     * JPEG, PNG and BMP pictures are re-encoded as WebP in the browser before they are sent: a photo
     * from a phone weighs several times less, and the eye hardly sees a difference at quality 85.
     * A picture that would not get lighter, GIFs (animation) and SVGs stay as they are, and so does
     * everything in a browser that cannot write WebP. The cropper writes its result as WebP too.
     *
     * On by default for picture fields (->image(), ->avatar(), ->crop()); a field that also takes
     * documents keeps its files unless this is called:
     *
     *     Upload::make('form.photo')->image()                     // WebP at 85
     *     Upload::make('form.photo')->image()->webp(quality: 92)  // finer
     *     Upload::make('form.logo')->image()->keepFormat()        // PNG stays PNG
     */
    public function webp(int $quality = self::WEBP_QUALITY): static
    {
        if ($quality < 1 || $quality > 100) {
            throw new VandaException("Upload [{$this->path()}]->webp({$quality}): the quality is a number from 1 to 100, e.g. ->webp(85).");
        }
        $this->webp = $quality;

        return $this;
    }

    /**
     * Pictures are sent as they are, never re-encoded as WebP (see webp()).
     */
    public function keepFormat(): static
    {
        $this->webp = false;

        return $this;
    }

    /**
     * The WebP quality the browser re-encodes pictures at, or null when they keep their format:
     * the one set with webp(), else 85 for a field that takes only pictures (and WebP among them).
     */
    public function webpQuality(): ?int
    {
        if ($this->webp === false) {
            return null;
        }

        $types = array_filter(array_map('trim', explode(',', strtolower((string) $this->accept))));
        $takesWebp = $types === [] || array_intersect($types, ['image/*', 'image/webp', '.webp']) !== [];

        if (is_int($this->webp)) {
            if (! $takesWebp) {
                throw new VandaException("Upload [{$this->path()}] converts pictures to WebP (->webp()), but ->accept('{$this->accept}') refuses WebP files. Add \"image/webp\" to accept(), or remove ->webp().");
            }

            return $this->webp;
        }

        $pictures = $types !== [] && array_filter($types, static fn (string $type) => ! str_starts_with($type, 'image/')
            && ! in_array($type, ['.jpg', '.jpeg', '.png', '.webp', '.gif', '.bmp', '.avif'], true)) === [];

        return ($pictures || $this->avatar !== null || $this->crops !== []) && $takesWebp ? self::WEBP_QUALITY : null;
    }

    /**
     * Folder on the disk instead of the dated one ("2026/01/15/"), e.g. 'posts'.
     */
    public function directory(string $directory): static
    {
        $this->directory = $directory;

        return $this;
    }

    /** Never stored: a web server could run them, or they rewrite its rules (any "php" with digits too). */
    private const FORBIDDEN = ['php', 'phtml', 'phtm', 'phar', 'pht', 'phps', 'cgi', 'pl', 'py', 'rb', 'sh', 'asp', 'aspx', 'jsp', 'jspx', 'htaccess', 'htpasswd'];

    /**
     * Served as a page or a script (web servers pick the type by extension): stored only when
     * ->accept() names them. A file without an extension gets one from its content first.
     */
    private const ACTIVE = ['html', 'htm', 'xhtml', 'xht', 'shtml', 'shtm', 'svg', 'svgz', 'xml', 'xsl', 'xslt', 'js', 'mjs', 'swf', 'hta', 'mht', 'mhtml'];

    /**
     * Checks the file against this field and stores it. The file is kept under its own extension
     * only when that extension is safe: a name the web server would run, or one a browser would
     * open as a page on the panel's domain, is refused (see ->accept()).
     *
     * @throws ValidationException
     */
    public function store(UploadedFile $file): Attachment
    {
        Validator::make(['file' => $file], ['file' => ['required', 'file', 'max:'.(int) round($this->maxSize * 1024)]])->validate();

        // "x.pHp " and "x.php." end up as .php on some systems: compare what a disk would keep.
        $extension = strtolower(pathinfo(rtrim($file->getClientOriginalName(), " .\t\n\r\0\x0B"), PATHINFO_EXTENSION));
        $extension = $extension === '' ? strtolower((string) $file->guessExtension()) : $extension;
        $mime = strtolower((string) $file->getMimeType());
        $types = array_filter(array_map('trim', explode(',', strtolower((string) $this->accept))));

        $forbidden = preg_match('/^[a-z0-9]{0,16}$/', $extension) !== 1
            || in_array($extension, self::FORBIDDEN, true)
            || preg_match('/^php\d+$/', $extension) === 1
            || str_starts_with(strtolower($file->getClientOriginalName()), '.ht');
        $active = in_array($extension, self::ACTIVE, true);
        $named = in_array('.'.$extension, $types, true) || in_array($mime, $types, true);

        if ($forbidden || ($active && ! $named)) {
            throw ValidationException::withMessages(['file' => [__('vanda::upload.forbidden')]]);
        }

        // Several accepted types mean "any of them".
        if ($types !== [] && array_filter($types, fn (string $type) => $this->matchesType($extension, $mime, $type)) === []) {
            throw ValidationException::withMessages(['file' => [__('vanda::upload.type', ['types' => $this->accept])]]);
        }

        return Attachment::store($file, $this->disk ?? (string) config('vanda.attachments.disk', 'public'), $this->group, Auth::id() === null ? null : (int) Auth::id(), $this->directory, $extension);
    }

    /**
     * What this field keeps for the attachment: its id or its URL (see target()).
     */
    public function valueOf(Attachment $attachment): int|string|null
    {
        return match ($this->target) {
            UploadTarget::Id => $attachment->id,
            UploadTarget::Url => $attachment->storageUrl(),
            UploadTarget::RelativeUrl => ($url = $attachment->storageUrl()) === null ? null : (string) parse_url($url, PHP_URL_PATH),
        };
    }

    /**
     * What the browser shows for these values: name, size, type and link of every file.
     * A URL with no attachment behind it (stored before Vanda) is shown by its file name.
     *
     * @param list<mixed> $values
     * @return list<array<string, mixed>>
     */
    public function describe(array $values): array
    {
        $values = $this->normalize($values);
        $found = [];
        foreach ($this->attachmentsFor($values) as $attachment) {
            $found[(string) $this->valueOf($attachment)] = $attachment;
        }

        return array_values(array_map(function (int|string $value) use ($found): array {
            $attachment = $found[(string) $value] ?? null;
            if ($attachment !== null) {
                return [...$attachment->toUpload(), 'value' => $value];
            }

            $name = basename((string) parse_url((string) $value, PHP_URL_PATH));
            $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

            return [
                'name' => $name,
                'mime' => MimeTypes::getDefault()->getMimeTypes($extension)[0] ?? 'application/octet-stream',
                'extension' => $extension === '' ? null : $extension,
                'url' => is_string($value) ? $value : null,
                'value' => $value,
            ];
        }, array_filter($values, static fn ($value) => is_string($value) || isset($found[(string) $value]))));
    }

    /**
     * Keeps ids that were already in the field and files this user has just uploaded,
     * so nobody can attach someone else's file by typing its id.
     *
     * @return int|list<int>|null
     */
    public function sanitize(mixed $value, mixed $current = null): int|string|array|null
    {
        $values = $this->normalize((array) $value);
        $known = $this->normalize((array) $current);
        $fresh = array_values(array_diff($values, $known));

        if ($fresh !== []) {
            $own = array_map(fn (Attachment $attachment) => $this->valueOf($attachment), array_filter(
                $this->attachmentsFor($fresh),
                static fn (Attachment $attachment) => $attachment->user_id === (Auth::id() === null ? null : (int) Auth::id()),
            ));
            $values = array_values(array_filter($values, static fn (int|string $item) => in_array($item, $known, true) || in_array($item, $own, true)));
        }

        if ($this->maxFiles !== null) {
            $values = array_slice($values, 0, $this->maxFiles);
        }

        return $this->multiple ? $values : ($values[0] ?? null);
    }

    protected function fieldProperties(SchemaContext $context): array
    {
        return [
            'multiple' => $this->multiple,
            'accept' => $this->accept,
            'maxSize' => $this->maxSize * 1024 * 1024,
            'maxFiles' => $this->maxFiles,
            'avatar' => $this->avatar === null ? null : ['initials' => Avatar::initials($this->avatar), 'hue' => Avatar::hue($this->avatar)],
            'files' => $this->describe((array) data_get($context->state, $this->path())),
            'crop' => $this->crops === [] ? null : array_map(static fn (Crop $crop) => $crop->toSchema(), $this->crops),
            'customCrop' => $this->customCrop,
            'webp' => $this->webpQuality(),
        ];
    }

    /**
     * Ids as integers, URLs as non-empty strings, each once.
     *
     * @param array<mixed> $values
     * @return list<int|string>
     */
    private function normalize(array $values): array
    {
        $values = $this->target === UploadTarget::Id
            ? array_map('intval', array_filter($values, 'is_numeric'))
            : array_filter($values, static fn ($value) => is_string($value) && $value !== '');

        return array_values(array_unique($values));
    }

    /**
     * Attachments behind these values. A URL is matched by the stored file name, then compared whole.
     *
     * @param list<int|string> $values
     * @return list<Attachment>
     */
    private function attachmentsFor(array $values): array
    {
        if ($values === []) {
            return [];
        }

        if ($this->target === UploadTarget::Id) {
            return Attachment::query()->whereKey($values)->get()->all();
        }

        $names = array_map(static fn ($url) => pathinfo((string) parse_url((string) $url, PHP_URL_PATH), PATHINFO_FILENAME), $values);

        return array_values(array_filter(
            Attachment::query()->whereIn('name', array_unique($names))->get()->all(),
            fn (Attachment $attachment) => in_array($this->valueOf($attachment), $values, true),
        ));
    }

    /**
     * "image/*" does not let SVG in: an SVG opened from the same site can run scripts.
     * Accept it explicitly with ".svg" or "image/svg+xml". A wildcard type also needs the file's
     * extension to be one of the type's own ("photo.jpg" with JPEG inside), so an image is never
     * stored under a name a browser reads as something else.
     */
    private function matchesType(string $extension, string $mime, string $type): bool
    {
        return match (true) {
            str_starts_with($type, '.') => $extension === substr($type, 1),
            str_ends_with($type, '/*') => str_starts_with($mime, substr($type, 0, -1))
                && $mime !== 'image/svg+xml'
                && in_array($extension, MimeTypes::getDefault()->getExtensions($mime), true),
            default => $mime === $type,
        };
    }
}
