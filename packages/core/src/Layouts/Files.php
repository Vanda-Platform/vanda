<?php

declare(strict_types=1);

namespace Vanda\Layouts;

use Closure;
use DateTimeInterface;
use Vanda\Actions\Button;
use Vanda\Actions\Link;
use Vanda\Actions\ModalToggle;
use Vanda\Schema\Node;
use Vanda\Schema\SchemaContext;
use Vanda\VandaException;

/**
 * A file browser: folders and files as tiles or a list, with thumbnails of images and an icon with
 * the extension for anything else. It knows nothing of where files live: the screen says which
 * folders and files to show and what a click does.
 *
 *     Layout::files()->id('files')
 *         ->folder('Certificates', Panel::url(LibraryScreen::class, ['folder' => $folder]), meta: '12 files')
 *         ->file((string) $file->id, $file->name, url: $file->url, mime: $file->mime, size: $file->size, date: $file->created_at)
 *         ->fileAction(fn (string $key) => ModalToggle::make('Open')->modal('file')->load($this->loadFile(...), ['id' => (int) $key]))
 *         ->selected($this->fileId !== null ? (string) $this->fileId : null)
 *         ->emptyText('The folder is empty')
 *         ->dependsOn('browser.q'),
 *
 * A click on a folder opens its address (through Inertia for a panel page; Cmd/Ctrl-click in a new
 * tab). A click on a file, or Enter on it, runs the node ->fileAction() returns for it, as
 * Table::rowAction() does for a row: a ModalToggle, a Button or a Link, its arguments signed for that
 * file. A file without one (or whose node the user may not use) is shown but opens nothing.
 * The toolbar switches between tiles and a list; the browser remembers the choice per node id.
 */
final class Files extends Node
{
    /** @var list<array{name: string, href: string, meta: ?string, hint: ?string}> */
    private array $folders = [];

    /** @var list<array{key: string, name: string, url: ?string, mime: ?string, extension: string, size: ?int, date: ?DateTimeInterface, hint: ?string}> */
    private array $files = [];

    private ?Closure $fileAction = null;

    private ?string $selected = null;

    private FilesLayout $layout = FilesLayout::Grid;

    private ?string $emptyText = null;

    public static function make(): self
    {
        return (new self)->captureSource();
    }

    public function type(): string
    {
        return 'files';
    }

    /**
     * A folder: a click opens `$href`, usually the same screen for that folder.
     *
     * @param string|null $meta under the name: "3 folders · 12 files"
     * @param string|null $hint where it lies, for search results: "in «Certificates»"
     */
    public function folder(string $name, string $href, ?string $meta = null, ?string $hint = null): self
    {
        $this->folders[] = ['name' => $name, 'href' => $href, 'meta' => $meta, 'hint' => $hint];

        return $this;
    }

    /**
     * A file. The size and the date are shown under the name; an image (but SVG) with a `$url` gets
     * its thumbnail, anything else an icon of its type with the extension on it.
     *
     * @param string $key what ->fileAction() and ->selected() know the file by, usually its id
     * @param string|null $url the file's address: the thumbnail of an image
     * @param string|null $extension without the dot; taken from the name when missing
     * @param int|null $size in bytes
     * @param string|null $hint where it lies, for search results: "in «Certificates»"
     */
    public function file(
        string $key,
        string $name,
        ?string $url = null,
        ?string $mime = null,
        ?string $extension = null,
        ?int $size = null,
        ?DateTimeInterface $date = null,
        ?string $hint = null,
    ): self {
        $extension = strtolower(ltrim((string) ($extension ?: pathinfo($name, PATHINFO_EXTENSION)), '.'));
        $this->files[] = compact('key', 'name', 'url', 'mime', 'extension', 'size', 'date', 'hint');

        return $this;
    }

    /**
     * What a click on a file (or Enter on it) runs: the closure gets the file's key and returns a
     * ModalToggle, a Button or a Link built for it, or null for a file that opens nothing. Its
     * arguments are signed for each file, as in Table::rowActions().
     *
     *     ->fileAction(fn (string $key) => ModalToggle::make('Open')->modal('file')->load($this->loadFile(...), ['id' => (int) $key]))
     *
     * @param Closure(string $key): (ModalToggle|Button|Link|null) $action
     */
    public function fileAction(Closure $action): self
    {
        $this->fileAction = $action;

        return $this;
    }

    /**
     * The file shown as chosen (a ring around its tile, aria-current).
     */
    public function selected(?string $key): self
    {
        $this->selected = $key;

        return $this;
    }

    /**
     * How the browser draws at first (tiles by default); the user's own choice wins once made.
     */
    public function layout(FilesLayout $layout): self
    {
        $this->layout = $layout;

        return $this;
    }

    /**
     * The text when there are no folders and no files (the renderer's «Nothing here yet» by default).
     */
    public function emptyText(string $text): self
    {
        $this->emptyText = $text;

        return $this;
    }

    /**
     * A size for people, in the application's language: 820 B, 14 KB, 3.4 MB (820 Б, 14 КБ, 3,4 МБ).
     */
    public static function size(?int $bytes): ?string
    {
        if ($bytes === null) {
            return null;
        }

        $units = __('vanda::files.units');
        $units = is_array($units) && count($units) === 4 ? array_values($units) : ['B', 'KB', 'MB', 'GB'];
        $value = (float) max(0, $bytes);
        $unit = 0;

        while ($value >= 1024 && $unit < 3) {
            $value /= 1024;
            $unit++;
        }

        $number = number_format($value, $unit === 0 || $value >= 10 ? 0 : 1, (string) __('vanda::files.decimal'), '');

        return $number.' '.$units[$unit];
    }

    /**
     * What kind of file it is, for the colour of its icon: image, video, audio, pdf, doc, sheet,
     * slides, archive or other.
     */
    public static function kind(?string $mime, string $extension): string
    {
        $mime = (string) $mime;

        return match (true) {
            str_starts_with($mime, 'image/') => 'image',
            str_starts_with($mime, 'video/') => 'video',
            str_starts_with($mime, 'audio/') => 'audio',
            $extension === 'pdf' || $mime === 'application/pdf' => 'pdf',
            in_array($extension, ['doc', 'docx', 'odt', 'rtf', 'txt'], true) => 'doc',
            in_array($extension, ['xls', 'xlsx', 'ods', 'csv'], true) => 'sheet',
            in_array($extension, ['ppt', 'pptx', 'odp', 'key'], true) => 'slides',
            in_array($extension, ['zip', 'rar', '7z', 'gz', 'tar'], true) => 'archive',
            default => 'other',
        };
    }

    protected function properties(SchemaContext $context): array
    {
        $files = [];
        foreach ($this->files as $file) {
            $action = null;

            if ($this->fileAction !== null) {
                $node = ($this->fileAction)($file['key']);

                if ($node !== null && ! $node instanceof ModalToggle && ! $node instanceof Button && ! $node instanceof Link) {
                    throw VandaException::invalidFileAction(get_debug_type($node));
                }

                if ($node !== null && $node->isVisible()) {
                    $action = $node->id($this->id.'.'.$file['key'].'.action')->toSchema($context);
                }
            }

            $image = str_starts_with((string) $file['mime'], 'image/') && $file['extension'] !== 'svg';

            $files[] = array_filter([
                'key' => $file['key'],
                'name' => $file['name'],
                'thumb' => $image ? $file['url'] : null,
                'kind' => self::kind($file['mime'], $file['extension']),
                'extension' => $file['extension'] !== '' ? mb_strtoupper(mb_substr($file['extension'], 0, 4)) : null,
                'meta' => implode(' · ', array_filter([self::size($file['size']), $file['date']?->format('d.m.Y')])) ?: null,
                'hint' => $file['hint'],
                'action' => $action,
            ], static fn ($value) => $value !== null);
        }

        return [
            'layout' => $this->layout->value,
            'folders' => array_map(static fn (array $folder) => array_filter($folder, static fn ($value) => $value !== null), $this->folders),
            'files' => $files,
            'selected' => $this->selected,
            'emptyText' => $this->emptyText,
        ];
    }
}
