<?php

declare(strict_types=1);

namespace Vanda\Fields;

use Vanda\Schema\SchemaContext;
use Vanda\Support\Html;

/**
 * Formatted text: headings, colours, lists, quotes, links, images, tables, video.
 * The value is HTML, cleaned on the server to what the editor produces (Vanda\Support\Html):
 *
 *     RichText::make('form.content')->label('Text'),
 *     RichText::make('form.body')->embeds(['rutube.ru', 'youtube.com']),   // allow video players
 *     RichText::make('form.body')->uploads(),                              // images from the computer
 *     RichText::make('form.note')->tags(['p', 'strong', 'em', 'a']),       // a stricter set
 *
 * The editor is told the same tags and styles, so it never offers what the server would drop.
 * Text the user did not change is saved as it was, so old markup (from Quill, say) is never
 * lost by just opening and saving a record. Markup the visual editor cannot keep opens in
 * HTML mode with a warning instead of being dropped silently.
 */
final class RichText extends Field implements StoresImages
{
    private int $minHeight = 200;

    /** @var array<string, list<string>>|null */
    private ?array $tags = null;

    /** @var list<string> */
    private array $embeds = [];

    private bool $htmlMode = true;

    /** Megabytes per image uploaded into the text; null when uploads are off. */
    private ?float $uploads = null;

    /** WebP quality pictures are re-encoded at before they go up; null keeps their format. */
    private ?int $uploadsWebp = Upload::WEBP_QUALITY;

    public function type(): string
    {
        return 'richtext';
    }

    /**
     * Height of the empty editor in pixels; it grows with the text.
     */
    public function minHeight(int $pixels): static
    {
        $this->minHeight = $pixels;

        return $this;
    }

    /**
     * Allowed tags instead of Html::TAGS: a list of names (attributes as in Html::TAGS)
     * or tag => attributes.
     *
     * @param list<string>|array<string, list<string>> $tags
     */
    public function tags(array $tags): static
    {
        $this->tags = [];
        foreach ($tags as $key => $value) {
            is_int($key) ? $this->tags[$value] = Html::TAGS[$value] ?? [] : $this->tags[$key] = $value;
        }

        return $this;
    }

    /**
     * Video and other <iframe> from these hosts are kept (https only), e.g. ['rutube.ru'].
     *
     * @param list<string> $hosts
     */
    public function embeds(array $hosts): static
    {
        $this->embeds = array_values($hosts);

        return $this;
    }

    /**
     * Whether the user may switch to editing the HTML itself (on by default).
     */
    public function sourceMode(bool $allowed = true): static
    {
        $this->htmlMode = $allowed;

        return $this;
    }

    /**
     * Images can be uploaded into the text (pasted, dropped or chosen in the image dialog).
     * They are stored like Upload files, through the screen's own URL, and the text keeps
     * their address ("/storage/2026/01/15/abc.jpg"). JPEG and PNG go up as WebP at this quality
     * (see Upload::webp()); `webp: null` keeps them as they are.
     */
    public function uploads(float $maxMegabytes = 5, ?int $webp = Upload::WEBP_QUALITY): static
    {
        $this->uploads = $maxMegabytes;
        $this->uploadsWebp = $webp;

        return $this;
    }

    /**
     * The Upload that stores images for this text, or null when ->uploads() is off.
     */
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

        return $value === $current ? $value : Html::clean($value, $this->tags, $this->embeds);
    }

    protected function fieldProperties(SchemaContext $context): array
    {
        $elements = $this->tags ?? Html::TAGS;
        if ($this->embeds !== []) {
            $elements['iframe'] = Html::IFRAME;
        }

        return [
            'minHeight' => $this->minHeight,
            'embeds' => $this->embeds,
            'htmlMode' => $this->htmlMode,
            'tags' => array_keys($elements),
            // The editor's schema: tag => attributes and the CSS properties the server keeps.
            'elements' => $elements,
            'styles' => array_keys(Html::STYLES),
            'uploads' => $this->uploads === null ? null : $this->uploads * 1024 * 1024,
            'webp' => $this->uploads === null ? null : $this->uploader()?->webpQuality(),
        ];
    }
}
