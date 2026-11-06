<?php

declare(strict_types=1);

namespace Vanda\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Keeps only the HTML a rich text editor produces: text formatting, headings, lists, quotes,
 * code, links, images, tables and text alignment. Scripts, styles, event handlers and
 * javascript: links are removed, so content saved from a RichText field is safe to print.
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
        'p' => ['style'], 'br' => [], 'hr' => [],
        'h1' => ['style'], 'h2' => ['style'], 'h3' => ['style'], 'h4' => ['style'],
        'strong' => [], 'b' => [], 'em' => [], 'i' => [], 'u' => [], 's' => [], 'code' => [], 'mark' => [], 'sub' => [], 'sup' => [],
        'blockquote' => [], 'pre' => [], 'ul' => [], 'ol' => ['start'], 'li' => [],
        'a' => ['href', 'target', 'rel'],
        'img' => ['src', 'alt', 'title', 'width', 'height'],
        'figure' => [], 'figcaption' => [],
        'table' => [], 'colgroup' => [], 'col' => ['style'], 'thead' => [], 'tbody' => [], 'tfoot' => [], 'tr' => [],
        'th' => ['colspan', 'rowspan', 'colwidth', 'style'], 'td' => ['colspan', 'rowspan', 'colwidth', 'style'],
    ];

    /** Kept on <iframe> when its host is allowed. */
    private const IFRAME = ['src', 'width', 'height', 'allow', 'allowfullscreen', 'frameborder', 'title'];

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

                if (! in_array($name, $tags[$tag], true) || (in_array($name, ['href', 'src'], true) && ! self::safeUrl($value))) {
                    $child->removeAttribute($attribute->nodeName);
                } elseif ($name === 'style') {
                    $style = self::safeStyle($value);
                    $style === '' ? $child->removeAttribute('style') : $child->setAttribute('style', $style);
                }
            }

            if ($tag === 'a' && $child->getAttribute('target') === '_blank') {
                $child->setAttribute('rel', 'noopener noreferrer');
            }
        }
    }

    /**
     * Only text alignment and column widths survive in a style attribute.
     */
    private static function safeStyle(string $style): string
    {
        $kept = [];
        foreach (explode(';', $style) as $declaration) {
            [$property, $value] = array_map('trim', explode(':', $declaration, 2) + [1 => '']);
            $property = strtolower($property);
            if ($property === 'text-align' && in_array(strtolower($value), ['left', 'right', 'center', 'justify'], true)) {
                $kept[] = 'text-align: '.strtolower($value);
            } elseif (in_array($property, ['width', 'min-width'], true) && preg_match('/^\d{1,4}(px|%)$/', $value) === 1) {
                $kept[] = $property.': '.$value;
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
