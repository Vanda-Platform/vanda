<?php

declare(strict_types=1);

namespace Vanda\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Keeps only the HTML a rich text editor produces: text formatting, colours, headings,
 * lists, quotes, code, links and anchors, images, tables and alignment. Scripts, event handlers,
 * javascript: links and any CSS beyond Html::STYLES are removed, so content saved from a RichText
 * field is safe to print.
 *
 *     Html::clean($html);                                   // the editor's tags (Html::TAGS)
 *     Html::clean($html, embedHosts: ['rutube.ru']);        // plus <iframe> from these hosts
 *     Html::clean($html, ['p' => [], 'a' => ['href']]);     // a stricter list of your own
 *
 * Quill's alignment classes (`ql-align-center`, as Orchid saved them) become `text-align`
 * so aligned paragraphs survive the move to the new editor.
 *
 * Text written in TinyMCE (Vanda\Fields\TinyMce) is cleaned wider, so its markup is not lost:
 *
 *     Html::cleanTinyMce($html, ['youtube.com', 'rutube.ru']);
 *
 * It keeps every tag of Html::TINYMCE_TAGS with classes, ids, data-* and aria-* attributes and any
 * CSS that loads nothing (no url(), expression(), @import) and does not pin itself over the page.
 */
final class Html
{
    /** Tags the editor produces and the attributes kept on them. */
    public const TAGS = [
        'p' => ['style'], 'br' => [], 'hr' => [], 'span' => ['style'],
        'h1' => ['style'], 'h2' => ['style'], 'h3' => ['style'], 'h4' => ['style'], 'h5' => ['style'], 'h6' => ['style'],
        'strong' => [], 'b' => [], 'em' => [], 'i' => [], 'u' => [], 's' => [], 'del' => [], 'ins' => [],
        'code' => [], 'mark' => [], 'sub' => [], 'sup' => [], 'small' => [],
        'blockquote' => ['style'], 'pre' => [], 'ul' => ['style'], 'ol' => ['start', 'style'], 'li' => ['style'],
        'a' => ['href', 'target', 'rel', 'title', 'id'],
        'img' => ['src', 'alt', 'title', 'width', 'height', 'style'],
        'figure' => ['style'], 'figcaption' => [],
        'table' => ['border', 'style'], 'caption' => [], 'colgroup' => [], 'col' => ['span', 'style'],
        'thead' => [], 'tbody' => [], 'tfoot' => [], 'tr' => ['style'],
        'th' => ['colspan', 'rowspan', 'scope', 'colwidth', 'style'], 'td' => ['colspan', 'rowspan', 'colwidth', 'style'],
    ];

    /**
     * CSS kept in a style attribute, with the values allowed: colours, alignment, sizes, indents,
     * list markers, image float and table borders. Nothing that loads a URL or runs code.
     */
    public const STYLES = [
        'color' => self::COLOR,
        'background-color' => self::COLOR,
        'text-align' => '/^(left|right|center|justify)$/i',
        'text-decoration' => '/^(none|underline|line-through)( (underline|line-through))?$/i',
        'font-weight' => '/^(normal|bold|[1-9]00)$/i',
        'font-style' => '/^(normal|italic)$/i',
        'font-size' => '/^\d{1,3}(\.\d{1,2})?(px|em|rem|%)$/i',
        'width' => self::LENGTH,
        'height' => self::LENGTH,
        'min-width' => self::LENGTH,
        'max-width' => self::LENGTH,
        'padding-left' => self::LENGTH,
        'margin-left' => self::LENGTH,
        'margin-right' => self::LENGTH,
        'float' => '/^(left|right|none)$/i',
        'display' => '/^(block|inline|inline-block)$/i',
        'vertical-align' => '/^(top|middle|bottom|baseline|text-top|text-bottom)$/i',
        'list-style-type' => '/^(none|disc|circle|square|decimal|lower-alpha|upper-alpha|lower-latin|upper-latin|lower-roman|upper-roman|lower-greek)$/i',
        'border-collapse' => '/^(collapse|separate)$/i',
        'border-width' => self::LENGTH,
        'border-style' => '/^(none|solid|dashed|dotted|double)$/i',
        'border-color' => self::COLOR,
    ];

    private const COLOR = '/^(#[0-9a-f]{3,8}|rgba?\(\s*\d{1,3}%?\s*,\s*\d{1,3}%?\s*,\s*\d{1,3}%?\s*(,\s*(0|1|0?\.\d+)\s*)?\)|[a-z]{3,20})$/i';

    private const LENGTH = '/^(0|auto|\d{1,4}(\.\d{1,3})?(px|%|em|rem))$/i';

    /** Kept on <iframe> when its host is allowed. */
    public const IFRAME = ['src', 'width', 'height', 'allow', 'allowfullscreen', 'frameborder', 'title'];

    /**
     * Tags TinyMCE writes (Vanda\Fields\TinyMce) and the attributes each keeps on top of
     * Html::TINYMCE_ATTRIBUTES, data-* and aria-*: wider than Html::TAGS, with blocks, media, the
     * accordion's <details> and the old attributes of tables and images (align, valign, bgcolor).
     */
    public const TINYMCE_TAGS = [
        'p' => ['align'], 'div' => ['align'], 'span' => [], 'br' => [], 'wbr' => [], 'hr' => ['size', 'width', 'noshade'],
        'h1' => ['align'], 'h2' => ['align'], 'h3' => ['align'], 'h4' => ['align'], 'h5' => ['align'], 'h6' => ['align'],
        'address' => [], 'article' => [], 'aside' => [], 'section' => [], 'header' => [], 'footer' => [], 'nav' => [], 'main' => [], 'hgroup' => [], 'center' => [],
        'blockquote' => ['cite'], 'pre' => [], 'code' => [], 'kbd' => [], 'samp' => [], 'var' => [],
        'strong' => [], 'b' => [], 'em' => [], 'i' => [], 'u' => [], 's' => [], 'strike' => [], 'del' => ['cite', 'datetime'], 'ins' => ['cite', 'datetime'],
        'mark' => [], 'sub' => [], 'sup' => [], 'small' => [], 'big' => [], 'abbr' => [], 'acronym' => [], 'cite' => [], 'dfn' => [], 'q' => ['cite'],
        'time' => ['datetime'], 'bdi' => [], 'bdo' => [], 'font' => ['color', 'size', 'face'],
        'ul' => ['type'], 'ol' => ['start', 'type', 'reversed'], 'li' => ['value', 'type'], 'dl' => [], 'dt' => [], 'dd' => [],
        'a' => ['href', 'target', 'rel', 'name', 'download', 'hreflang', 'type', 'referrerpolicy'],
        'img' => ['src', 'srcset', 'sizes', 'alt', 'width', 'height', 'loading', 'decoding', 'align', 'border', 'hspace', 'vspace'],
        'picture' => [], 'source' => ['src', 'srcset', 'sizes', 'type', 'media', 'width', 'height'], 'figure' => [], 'figcaption' => [],
        'video' => ['src', 'poster', 'width', 'height', 'controls', 'controlslist', 'autoplay', 'muted', 'loop', 'playsinline', 'preload'],
        'audio' => ['src', 'controls', 'controlslist', 'autoplay', 'muted', 'loop', 'preload'],
        'track' => ['src', 'kind', 'srclang', 'label', 'default'],
        'table' => ['border', 'cellpadding', 'cellspacing', 'width', 'height', 'align', 'summary', 'bgcolor', 'frame', 'rules'],
        'caption' => ['align'], 'colgroup' => ['span', 'width', 'align', 'valign'], 'col' => ['span', 'width', 'align', 'valign'],
        'thead' => ['align', 'valign'], 'tbody' => ['align', 'valign'], 'tfoot' => ['align', 'valign'], 'tr' => ['align', 'valign', 'height', 'bgcolor'],
        'th' => ['colspan', 'rowspan', 'scope', 'headers', 'abbr', 'width', 'height', 'align', 'valign', 'bgcolor', 'nowrap'],
        'td' => ['colspan', 'rowspan', 'headers', 'abbr', 'width', 'height', 'align', 'valign', 'bgcolor', 'nowrap'],
        'details' => ['open'], 'summary' => [],
    ];

    /** Kept on every tag of Html::TINYMCE_TAGS, together with data-* and aria-* attributes. */
    public const TINYMCE_ATTRIBUTES = ['id', 'class', 'style', 'title', 'lang', 'dir', 'role'];

    /** Kept on an <iframe> of TinyMCE text when its host is allowed. */
    public const TINYMCE_IFRAME = [...self::IFRAME, 'name', 'loading', 'referrerpolicy', 'scrolling', 'marginwidth', 'marginheight'];

    /** Attributes holding an address: kept only when it is safe (no javascript:, no data: but raster images). */
    private const URL_ATTRIBUTES = ['href', 'src', 'poster', 'cite'];

    /** Removed together with everything inside them. */
    private const DROP = ['script', 'style', 'object', 'embed', 'template', 'noscript', 'svg', 'math', 'form', 'input', 'textarea', 'select', 'button', 'link', 'meta', 'base'];

    /**
     * @param array<string, list<string>>|null $tags tag => kept attributes; null for Html::TAGS
     * @param list<string> $embedHosts hosts whose <iframe> are kept, e.g. ['rutube.ru', 'www.youtube.com']
     */
    public static function clean(string $html, ?array $tags = null, array $embedHosts = []): string
    {
        if (trim($html) === '') {
            return '';
        }

        $tags ??= self::TAGS;
        if ($embedHosts !== []) {
            $tags['iframe'] = self::IFRAME;
        }

        return self::run($html, $tags, $embedHosts, false);
    }

    /**
     * Cleans text written in TinyMCE without losing its markup: the tags of Html::TINYMCE_TAGS,
     * classes, ids, data-* and aria-* attributes, legacy table attributes, <video>, the page break
     * (<!-- pagebreak -->) and pictures embedded as data:image (raster only). It removes what runs
     * code or loads something unasked: scripts, styles, forms, event handlers, javascript: links,
     * CSS with url()/expression()/@import, position: fixed or sticky, and <iframe> from other hosts.
     *
     *     Html::cleanTinyMce($html, embedHosts: ['youtube.com', 'rutube.ru']);
     *
     * @param list<string> $embedHosts hosts whose <iframe> are kept (https or protocol-relative)
     */
    public static function cleanTinyMce(string $html, array $embedHosts = []): string
    {
        if (trim($html) === '') {
            return '';
        }

        $tags = self::TINYMCE_TAGS;
        if ($embedHosts !== []) {
            $tags['iframe'] = self::TINYMCE_IFRAME;
        }

        // libxml does not know the void tags of HTML5 and closes them after their siblings.
        return preg_replace('~</(source|track|wbr)>~i', '', self::run($html, $tags, $embedHosts, true)) ?? '';
    }

    /**
     * @param array<string, list<string>> $tags
     * @param list<string> $embedHosts
     */
    private static function run(string $html, array $tags, array $embedHosts, bool $wide): string
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8"?><div id="vanda-root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $document->getElementById('vanda-root');
        if ($root === null) {
            return '';
        }

        self::walk($root, $tags, $embedHosts, $wide);

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $document->saveHTML($child);
        }

        return $out;
    }

    /**
     * Tags of $html that $tags would remove (to warn before it happens).
     *
     * @param array<string, list<string>>|null $tags
     * @return list<string>
     */
    public static function unsupported(string $html, ?array $tags = null): array
    {
        preg_match_all('/<([a-z][a-z0-9]*)\b/i', $html, $matches);
        $allowed = array_keys($tags ?? self::TAGS);

        return array_values(array_unique(array_diff(array_map('strtolower', $matches[1]), $allowed, ['div', 'span'])));
    }

    /**
     * @param array<string, list<string>> $tags
     * @param list<string> $embedHosts
     * @param bool $wide TinyMCE text (cleanTinyMce()): global attributes, wider CSS, the page break
     */
    private static function walk(DOMNode $node, array $tags, array $embedHosts, bool $wide = false): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child->nodeType === XML_COMMENT_NODE && $wide && trim((string) $child->nodeValue) === 'pagebreak') {
                continue;
            }

            if ($child->nodeType === XML_COMMENT_NODE || $child->nodeType === XML_PI_NODE) {
                $node->removeChild($child);

                continue;
            }

            if (! $child instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($child->tagName);

            if (in_array($tag, self::DROP, true) || ($tag === 'iframe' && ! self::allowedEmbed($child->getAttribute('src'), $embedHosts, $wide))) {
                $node->removeChild($child);

                continue;
            }

            self::walk($child, $tags, $embedHosts, $wide);

            if ($wide && array_key_exists($tag, $tags)) {
                self::keepTinyMceAttributes($child, $tag, $tags[$tag]);

                // A span without attributes is just noise (TinyMCE drops it too).
                if ($tag === 'span' && ! $child->hasAttributes()) {
                    while ($child->firstChild !== null) {
                        $node->insertBefore($child->firstChild, $child);
                    }
                    $node->removeChild($child);
                }

                continue;
            }

            if (! array_key_exists($tag, $tags)) {
                // Unknown wrapper (span, div, font): keep its content, drop the tag.
                while ($child->firstChild !== null) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);

                continue;
            }

            if (preg_match('/\bql-align-(left|right|center|justify)\b/', $child->getAttribute('class'), $align) === 1) {
                $child->setAttribute('style', 'text-align: '.$align[1].'; '.$child->getAttribute('style'));
            }

            foreach (iterator_to_array($child->attributes ?? []) as $attribute) {
                $name = strtolower($attribute->nodeName);
                $value = $attribute->nodeValue ?? '';

                if (! in_array($name, $tags[$tag], true)
                    || (in_array($name, ['href', 'src'], true) && ! self::safeUrl($value))
                    || ($name === 'id' && preg_match('/^[a-z][\w-]{0,63}$/i', $value) !== 1)) {
                    $child->removeAttribute($attribute->nodeName);
                } elseif ($name === 'style') {
                    $style = self::safeStyle($value);
                    $style === '' ? $child->removeAttribute('style') : $child->setAttribute('style', $style);
                }
            }

            if ($tag === 'a' && $child->getAttribute('target') === '_blank') {
                $child->setAttribute('rel', 'noopener noreferrer');
            }

            // A span is kept only for its style (a colour, say); without one it is just noise.
            if ($tag === 'span' && ! $child->hasAttributes()) {
                while ($child->firstChild !== null) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);
            }
        }
    }

    /**
     * The attributes of a tag of TinyMCE text: its own ($allowed), the global ones, data-* and
     * aria-*, each with a value that is safe to print.
     *
     * @param list<string> $allowed
     */
    private static function keepTinyMceAttributes(DOMElement $element, string $tag, array $allowed): void
    {
        if (preg_match('/\bql-align-(left|right|center|justify)\b/', $element->getAttribute('class'), $align) === 1) {
            $element->setAttribute('style', 'text-align: '.$align[1].'; '.$element->getAttribute('style'));
        }

        foreach (iterator_to_array($element->attributes ?? []) as $attribute) {
            $name = strtolower($attribute->nodeName);
            $value = $attribute->nodeValue ?? '';
            $known = in_array($name, $allowed, true) || in_array($name, self::TINYMCE_ATTRIBUTES, true)
                || (preg_match('/^(data|aria)-[a-z0-9][\w.:-]*$/', $name) === 1 && ! str_starts_with($name, 'data-mce-'));

            $clean = match (true) {
                ! $known => null,
                in_array($name, self::URL_ATTRIBUTES, true) => self::safeUrl($value) || ($tag === 'img' && $name === 'src' && self::rasterDataUri($value)) ? $value : null,
                $name === 'srcset' => self::safeSrcset($value) ? $value : null,
                $name === 'id', $name === 'name' => preg_match('/^[a-z][\w:.-]{0,127}$/i', $value) === 1 ? $value : null,
                $name === 'class' => implode(' ', array_filter(preg_split('/\s+/', trim($value)) ?: [], static fn (string $class) => preg_match('/^-?[_a-z][\w-]{0,63}$/i', $class) === 1)),
                $name === 'style' => self::wideStyle($value),
                default => $value,
            };

            if ($clean === null || ($clean === '' && in_array($name, ['class', 'style'], true))) {
                $element->removeAttribute($attribute->nodeName);
            } elseif ($clean !== $value) {
                $element->setAttribute($attribute->nodeName, $clean);
            }
        }

        // A new tab must not reach back to this page: noopener joins whatever rel the link had.
        if ($tag === 'a' && strtolower($element->getAttribute('target')) === '_blank') {
            $rel = preg_split('/\s+/', strtolower(trim($element->getAttribute('rel')))) ?: [];
            $element->setAttribute('rel', implode(' ', array_values(array_unique(array_filter([...$rel, 'noopener'])))));
        }
    }

    /**
     * Any CSS property but one that loads something or runs code (url(), expression(), image-set(),
     * @import, escapes) or pins itself over the page (position: fixed, sticky).
     */
    private static function wideStyle(string $style): string
    {
        $kept = [];
        foreach (explode(';', $style) as $declaration) {
            [$property, $value] = array_map('trim', explode(':', $declaration, 2) + [1 => '']);
            $property = strtolower($property);
            $value = preg_replace('/\s+/', ' ', $value) ?? '';

            if ($value === ''
                || preg_match('/^-?[a-z][a-z0-9-]*$/', $property) !== 1
                || in_array($property, ['behavior', '-moz-binding'], true)
                || ($property === 'position' && preg_match('/^(static|relative)$/i', $value) !== 1)
                || preg_match('/(url|expression|image-set|image|cross-fade|element|paint)\s*\(|javascript:|vbscript:|@import|\\\\|[<>{}]|\/\*/i', $value) === 1) {
                continue;
            }

            $kept[$property] = $property.': '.$value;
        }

        return implode('; ', $kept);
    }

    /** A picture pasted into the text as data (PNG, JPEG, GIF, WebP, AVIF, BMP), never SVG. */
    private static function rasterDataUri(string $url): bool
    {
        return preg_match('~^data:image/(png|jpe?g|gif|webp|avif|bmp);base64,[a-z0-9+/=\s]+$~i', trim($url)) === 1;
    }

    /** Every address of a srcset ("a.jpg 1x, b.jpg 2x") is safe. */
    private static function safeSrcset(string $srcset): bool
    {
        foreach (explode(',', $srcset) as $candidate) {
            $url = (preg_split('/\s+/', trim($candidate)) ?: [''])[0];
            if ($url !== '' && ! self::safeUrl($url)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Only the properties of Html::STYLES with a value their pattern accepts survive.
     */
    private static function safeStyle(string $style): string
    {
        $kept = [];
        foreach (explode(';', $style) as $declaration) {
            [$property, $value] = array_map('trim', explode(':', $declaration, 2) + [1 => '']);
            $property = strtolower($property);
            $value = preg_replace('/\s+/', ' ', $value) ?? '';
            $pattern = self::STYLES[$property] ?? null;

            if ($pattern !== null && $value !== '' && preg_match($pattern, $value) === 1) {
                $kept[$property] = $property.': '.$value;
            }
        }

        return implode('; ', $kept);
    }

    /**
     * @param list<string> $hosts
     * @param bool $protocolRelative "//www.youtube.com/embed/…", as older TinyMCE wrote it, counts as https
     */
    private static function allowedEmbed(string $src, array $hosts, bool $protocolRelative = false): bool
    {
        $src = trim($src);
        if ($protocolRelative && str_starts_with($src, '//')) {
            $src = 'https:'.$src;
        }

        $host = strtolower((string) parse_url($src, PHP_URL_HOST));
        $scheme = strtolower((string) parse_url($src, PHP_URL_SCHEME));

        if ($host === '' || $scheme !== 'https') {
            return false;
        }

        foreach ($hosts as $allowed) {
            $allowed = strtolower($allowed);
            if ($host === $allowed || str_ends_with($host, '.'.$allowed)) {
                return true;
            }
        }

        return false;
    }

    private static function safeUrl(string $url): bool
    {
        $url = trim(preg_replace('/[\x00-\x20]+/', '', $url) ?? '');

        return preg_match('~^(https?:|mailto:|tel:|/|#|\?)~i', $url) === 1 || preg_match('~^[a-z][a-z0-9+.-]*:~i', $url) !== 1;
    }
}
