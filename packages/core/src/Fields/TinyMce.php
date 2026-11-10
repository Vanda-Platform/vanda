<?php

declare(strict_types=1);

namespace Vanda\Fields;

use Closure;
use Vanda\Schema\SchemaContext;
use Vanda\Support\Html;
use Vanda\VandaException;

/**
 * An HTML editor on TinyMCE 6 (MIT), for texts written in TinyMCE before (Orchid's editor): their
 * classes, styles, tables and video stay as they were. The value is the HTML, in the same column.
 *
 *     TinyMce::make('form.body')->label('Текст')->height(500),
 *     TinyMce::make('form.body')->uploads(5),                       // pictures from the computer, up to 5 MB
 *     TinyMce::make('form.body')->linkList(fn () => Product::query()->orderBy('name')->get()
 *         ->map(fn (Product $product) => ['title' => $product->name, 'value' => '/catalog/'.$product->slug])
 *         ->all()),                                                  // pages to pick in the link dialog
 *
 * The server cleans the text with Html::cleanTinyMce(): what TinyMCE writes stays (classes, ids,
 * data-* attributes, CSS, tables, <video>, <iframe> from ->embeds() hosts), what runs code goes.
 * Text the user did not change is saved as it was. For new texts RichText keeps a stricter markup.
 */
final class TinyMce extends Field implements StoresImages
{
    /** Plugins of the editor unless ->plugins() says otherwise (as Orchid's TinyMCE had them). */
    public const PLUGINS = [
        'preview', 'searchreplace', 'autolink', 'autosave', 'code', 'visualblocks', 'visualchars', 'fullscreen', 'image', 'link',
        'media', 'codesample', 'table', 'charmap', 'pagebreak', 'nonbreaking', 'anchor', 'insertdatetime', 'advlist', 'lists',
        'wordcount', 'help', 'quickbars', 'emoticons',
    ];

    /** The toolbar unless ->toolbar() says otherwise; "|" divides groups. */
    public const TOOLBAR = 'undo redo | blocks fontsize | bold italic underline strikethrough | forecolor backcolor'
        .' | alignleft aligncenter alignright alignjustify | numlist bullist outdent indent | link image media table'
        .' | charmap emoticons | removeformat | code fullscreen preview';

    /** Video players kept in the text unless ->embeds() says otherwise. */
    public const VIDEO_HOSTS = ['youtube.com', 'youtube-nocookie.com', 'rutube.ru', 'vk.com', 'vkvideo.ru', 'vimeo.com', 'dzen.ru'];

    private int $height = 400;

    /** @var list<string> */
    private array $plugins = self::PLUGINS;

    /** @var string|list<string> */
    private string|array $toolbar = self::TOOLBAR;

    private bool|string $menubar = true;

    /** @var iterable<mixed>|Closure|null */
    private iterable|Closure|null $linkList = null;

    /** @var list<string> */
    private array $embeds = self::VIDEO_HOSTS;

    /** Megabytes per image uploaded into the text; null when uploads are off. */
    private ?float $uploads = null;

    /** WebP quality pictures are re-encoded at before they go up; null keeps their format. */
    private ?int $uploadsWebp = Upload::WEBP_QUALITY;

    /** @var list<string>|null */
    private static ?array $bundled = null;

    public function type(): string
    {
        return 'tinymce';
    }

    /**
     * Height of the whole editor (menu, toolbar and text) in pixels.
     */
    public function height(int $pixels): static
    {
        $this->height = $pixels;

        return $this;
    }

    /**
     * Plugins instead of TinyMce::PLUGINS; only those the renderer bundles
     * (TinyMce::bundledPlugins()), any other fails the page check.
     *
     *     ->plugins([...TinyMce::PLUGINS, 'autoresize'])
     *
     * @param list<string> $plugins
     */
    public function plugins(array $plugins): static
    {
        $this->plugins = array_values($plugins);

        return $this;
    }

    /**
     * Toolbar buttons instead of TinyMce::TOOLBAR, in TinyMCE's words: "bold italic | link".
     * A list gives several rows; an empty string hides the toolbar.
     *
     * @param string|list<string> $toolbar
     */
    public function toolbar(string|array $toolbar): static
    {
        $this->toolbar = is_array($toolbar) ? array_values($toolbar) : $toolbar;

        return $this;
    }

    /**
     * The menu above the toolbar: true for TinyMCE's (the default), false for none, or the menus
     * to show: "edit insert format table".
     */
    public function menubar(bool|string $menubar): static
    {
        $this->menubar = $menubar;

        return $this;
    }

    /**
     * Pages to pick in the link dialog ("Link list"): [['title' => …, 'value' => url], …]; a group
     * is ['title' => …, 'menu' => [ …links… ]]. A closure is called when the page is drawn.
     *
     *     ->linkList(fn () => Product::query()->orderBy('name')->get()
     *         ->map(fn (Product $product) => ['title' => $product->name, 'value' => '/catalog/'.$product->slug])->all())
     *
     * @param iterable<mixed>|Closure(): iterable<mixed> $links
     */
    public function linkList(iterable|Closure $links): static
    {
        $this->linkList = $links;

        return $this;
    }

    /**
     * Images can be uploaded into the text (pasted, dropped or chosen on the «Upload» tab of the
     * image dialog). They are stored like Upload files, through the screen's own URL, and the text
     * keeps their address ("/storage/2026/01/15/abc.jpg"). JPEG and PNG go up as WebP at this
     * quality (see Upload::webp()); `webp: null` keeps them as they are.
     */
    public function uploads(float $maxMegabytes = 5, ?int $webp = Upload::WEBP_QUALITY): static
    {
        $this->uploads = $maxMegabytes;
        $this->uploadsWebp = $webp;

        return $this;
    }

    /**
     * Hosts whose video players (<iframe>, https) the text keeps, instead of TinyMce::VIDEO_HOSTS;
     * [] keeps none.
     *
     * @param list<string> $hosts
     */
    public function embeds(array $hosts): static
    {
        $this->embeds = array_values($hosts);

        return $this;
    }

    public function uploader(): ?Upload
    {
        return $this->uploads === null
            ? null
            : tap(Upload::make($this->path())->image()->maxSize($this->uploads)->group('richtext')->target(UploadTarget::RelativeUrl),
                fn (Upload $upload) => $this->uploadsWebp === null ? $upload->keepFormat() : $upload->webp($this->uploadsWebp));
    }

    public function sanitize(mixed $value, mixed $current = null): string
    {
        if (! is_string($value)) {
            return '';
        }

        return $value === $current ? $value : Html::cleanTinyMce($value, $this->embeds);
    }

    /**
     * Plugins the renderer bundles (resources/tinymce-plugins.json, kept in step with the
     * renderer's imports by its tinymce test).
     *
     * @return list<string>
     */
    public static function bundledPlugins(): array
    {
        if (self::$bundled === null) {
            $file = dirname(__DIR__, 2).'/resources/tinymce-plugins.json';
            $names = is_file($file) ? json_decode((string) file_get_contents($file), true) : [];
            self::$bundled = is_array($names) ? array_values(array_filter($names, 'is_string')) : [];
        }

        return self::$bundled;
    }

    protected function fieldProperties(SchemaContext $context): array
    {
        return [
            'height' => $this->height,
            'plugins' => $this->plugins,
            // An empty toolbar or menu stays in the IR as "" (false and [] are left out of it).
            'toolbar' => $this->toolbar === [] ? '' : $this->toolbar,
            'menubar' => match ($this->menubar) {
                true => null,
                false => '',
                default => $this->menubar,
            },
            'linkList' => $this->linkList === null ? null : $this->links($this->linkList instanceof Closure ? ($this->linkList)() : $this->linkList),
            'uploads' => $this->uploads === null ? null : $this->uploads * 1024 * 1024,
            'webp' => $this->uploads === null ? null : $this->uploader()?->webpQuality(),
        ];
    }

    /**
     * @param mixed $links
     * @return list<array{title: string, value?: string, menu?: list<mixed>}>
     */
    private function links(mixed $links, int $depth = 0): array
    {
        if (! is_iterable($links)) {
            throw VandaException::invalidLinkList($this->path(), 'the list is '.get_debug_type($links));
        }

        $clean = [];
        foreach ($links as $index => $link) {
            $title = is_array($link) ? ($link['title'] ?? null) : null;
            $value = is_array($link) ? ($link['value'] ?? null) : null;
            $menu = is_array($link) ? ($link['menu'] ?? null) : null;

            if (! is_string($title) && ! is_int($title)) {
                throw VandaException::invalidLinkList($this->path(), sprintf('item %s has no "title"', var_export($index, true)));
            }

            if ($menu !== null && $depth < 5) {
                $clean[] = ['title' => (string) $title, 'menu' => $this->links($menu, $depth + 1)];
            } elseif (is_string($value)) {
                $clean[] = ['title' => (string) $title, 'value' => $value];
            } else {
                throw VandaException::invalidLinkList($this->path(), sprintf('item "%s" has no "value" (the address) and no "menu"', $title));
            }
        }

        return $clean;
    }
}
