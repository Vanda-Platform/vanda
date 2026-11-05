<?php

declare(strict_types=1);

namespace Vanda\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Keeps only the HTML a rich text editor produces: text formatting, headings, lists,
 * quotes, code and links. Scripts, styles, event handlers and javascript: links are removed,
 * so content saved from a RichText field is safe to print on a site.
 */
final class Html
{
    /** Allowed tags and their allowed attributes. */
    private const TAGS = [
        'p' => [], 'br' => [], 'hr' => [],
        'h1' => [], 'h2' => [], 'h3' => [], 'h4' => [],
        'strong' => [], 'b' => [], 'em' => [], 'i' => [], 'u' => [], 's' => [], 'code' => [], 'mark' => [],
        'blockquote' => [], 'pre' => [], 'ul' => [], 'ol' => ['start'], 'li' => [],
        'a' => ['href', 'target', 'rel'], 'img' => ['src', 'alt', 'title'],
    ];

    /** Removed together with everything inside them. */
    private const DROP = ['script', 'style', 'iframe', 'object', 'embed', 'template', 'noscript', 'svg', 'math', 'form', 'input', 'textarea', 'select', 'button'];

    public static function clean(string $html): string
    {
        if (trim($html) === '') {
            return '';
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

        self::walk($root);

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $document->saveHTML($child);
        }

        return $out;
    }

    private static function walk(DOMNode $node): void
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

            if (in_array($tag, self::DROP, true)) {
                $node->removeChild($child);

                continue;
            }

            self::walk($child);

            if (! array_key_exists($tag, self::TAGS)) {
                // Unknown wrapper (span, div, font): keep its content, drop the tag.
                while ($child->firstChild !== null) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);

                continue;
            }

            foreach (iterator_to_array($child->attributes ?? []) as $attribute) {
                $name = strtolower($attribute->nodeName);
                if (! in_array($name, self::TAGS[$tag], true) || (in_array($name, ['href', 'src'], true) && ! self::safeUrl($attribute->nodeValue ?? ''))) {
                    $child->removeAttribute($attribute->nodeName);
                }
            }

            if ($tag === 'a' && $child->getAttribute('target') === '_blank') {
                $child->setAttribute('rel', 'noopener noreferrer');
            }
        }
    }

    private static function safeUrl(string $url): bool
    {
        $url = trim(preg_replace('/[\x00-\x20]+/', '', $url) ?? '');

        return preg_match('~^(https?:|mailto:|tel:|/|#|\?)~i', $url) === 1 || preg_match('~^[a-z][a-z0-9+.-]*:~i', $url) !== 1;
    }
}
