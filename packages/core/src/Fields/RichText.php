<?php

declare(strict_types=1);

namespace Vanda\Fields;

use Vanda\Schema\SchemaContext;
use Vanda\Support\Html;

/**
 * Formatted text (Tiptap): headings, lists, quotes, links, images, tables and alignment.
 * The value is HTML, cleaned on the server to what the editor produces:
 *
 *     RichText::make('form.content')->label('Text'),
 *     RichText::make('form.body')->embeds(['rutube.ru', 'youtube.com']),   // allow video players
 *     RichText::make('form.note')->tags(['p', 'strong', 'em', 'a']),       // a stricter set
 *
 * Text the user did not change is saved as it was, so old markup (from Quill, say) is never
 * lost by just opening and saving a record. Markup the visual editor cannot keep opens in
 * HTML mode with a warning instead of being dropped silently.
 */
final class RichText extends Field
{
    private int $minHeight = 200;

    /** @var array<string, list<string>>|null */
    private ?array $tags = null;

    /** @var list<string> */
    private array $embeds = [];

    private bool $htmlMode = true;

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

    public function sanitize(mixed $value, mixed $current = null): string
    {
        if (! is_string($value)) {
            return '';
        }

        return $value === $current ? $value : Html::clean($value, $this->tags, $this->embeds);
    }

    protected function fieldProperties(SchemaContext $context): array
    {
        return [
            'minHeight' => $this->minHeight,
            'embeds' => $this->embeds,
            'htmlMode' => $this->htmlMode,
            'tags' => array_keys($this->tags ?? Html::TAGS),
        ];
    }
}
