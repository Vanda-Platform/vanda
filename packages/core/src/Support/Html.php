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

        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8"?><div id="vanda-root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $document->getElementById('vanda-root');
        if ($root === null) {
            return '';
        }

        self::walk($root, $tags, $embedHosts);

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
     */
    private static function walk(DOMNode $node, array $tags, array $embedHosts): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child->nodeType === XML_COMMENT_NODE || $child->nodeType === XML_PI_NODE) {
                $node->removeChild($child);

                continue;
            }

            if (! $child instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($child->tagName);

            if (in_array($tag, self::DROP, true) || ($tag === 'iframe' && ! self::allowedEmbed($child->getAttribute('src'), $embedHosts))) {
                $node->removeChild($child);

                continue;
            }

            self::walk($child, $tags, $embedHosts);

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
     */
    private static function allowedEmbed(string $src, array $hosts): bool
    {
        $host = strtolower((string) parse_url(trim($src), PHP_URL_HOST));
        $scheme = strtolower((string) parse_url(trim($src), PHP_URL_SCHEME));

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
