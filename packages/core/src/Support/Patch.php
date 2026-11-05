<?php

declare(strict_types=1);

namespace Vanda\Support;

/**
 * Applies reaction patches to a page IR, the same way the renderer does.
 */
final class Patch
{
    /**
     * @param array<string, mixed> $page
     * @param list<array{op: string, path: string, value: mixed}> $patches
     * @return array<string, mixed>
     */
    public static function apply(array $page, array $patches): array
    {
        foreach ($patches as $patch) {
            $segments = array_map(
                static fn (string $s) => str_replace(['~1', '~0'], ['/', '~'], $s),
                explode('/', ltrim($patch['path'], '/')),
            );
            $root = array_shift($segments);

            if ($root === 'nodes') {
                $id = implode('/', $segments);
                $page['commandBar'] = self::replaceNode($page['commandBar'], $id, $patch['value']);
                $page['layout'] = self::replaceNode($page['layout'], $id, $patch['value']);
            } elseif ($root === 'state') {
                data_set($page['state'], implode('.', $segments), $patch['value']);
            }
        }

        return $page;
    }

    /**
     * @param list<array<string, mixed>> $nodes
     * @param array<string, mixed>|null $replacement
     * @return list<array<string, mixed>>
     */
    private static function replaceNode(array $nodes, string $id, ?array $replacement): array
    {
        $result = [];

        foreach ($nodes as $node) {
            if (($node['id'] ?? null) === $id) {
                if ($replacement !== null) {
                    $result[] = $replacement;
                }

                continue;
            }

            if (isset($node['children'])) {
                $node['children'] = self::replaceNode($node['children'], $id, $replacement);
            }

            $result[] = $node;
        }

        return $result;
    }
}
