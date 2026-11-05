<?php

declare(strict_types=1);

namespace Vanda\Schema;

use Vanda\Fields\Field;
use Vanda\Layouts\Modal;
use Vanda\Support\Paths;

/**
 * The node tree of a screen with stable ids.
 *
 * Nodes without an explicit id get one from their position ("layout.0.1"),
 * fields use their path, buttons "action.<method>".
 */
final class Tree
{
    /** @var array<string, Node> */
    private array $nodes = [];

    /** @var array<string, list<Node>> */
    private array $ancestors = [];

    /**
     * @param list<Node> $commandBar
     * @param list<Node> $layout
     */
    public function __construct(
        public readonly array $commandBar,
        public readonly array $layout,
    ) {
        $this->index($commandBar, 'commandBar', []);
        $this->index($layout, 'layout', []);
    }

    /**
     * @return array<string, Node>
     */
    public function nodes(): array
    {
        return $this->nodes;
    }

    public function find(string $id): ?Node
    {
        return $this->nodes[$id] ?? null;
    }

    /**
     * @return list<Field>
     */
    public function fields(): array
    {
        return array_values(array_filter($this->nodes, static fn (Node $node) => $node instanceof Field));
    }

    /**
     * Fields a button validates: inside a modal, only that modal's fields;
     * anywhere else, every field that is not inside a modal.
     *
     * @return list<Field>
     */
    public function fieldsInScope(?string $triggerId): array
    {
        $modal = null;
        foreach ($this->ancestors[(string) $triggerId] ?? [] as $ancestor) {
            if ($ancestor instanceof Modal) {
                $modal = $ancestor;
            }
        }

        return array_values(array_filter($this->fields(), function (Field $field) use ($modal): bool {
            $modals = array_filter($this->ancestors[(string) $field->getId()] ?? [], static fn (Node $node) => $node instanceof Modal);

            return $modal === null ? $modals === [] : in_array($modal, $modals, true);
        }));
    }

    /**
     * Visible on the server and, given the state, in the browser too
     * (its own and all ancestors' visibleWhen are true).
     *
     * @param array<string, mixed> $state
     */
    public function isShown(Node $node, array $state): bool
    {
        foreach ([...($this->ancestors[(string) $node->getId()] ?? []), $node] as $current) {
            if (! $current->isVisible() || ($current->getVisibleWhen()?->isTrue($state) === false)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Nodes that must be re-resolved because a path they depend on changed.
     *
     * @param list<string> $changed
     * @return list<Node>
     */
    public function dependents(array $changed): array
    {
        return array_values(array_filter($this->nodes, static function (Node $node) use ($changed): bool {
            foreach ($node->getDependsOn() as $dependency) {
                foreach ($changed as $path) {
                    if (Paths::matches($dependency, $path)) {
                        return true;
                    }
                }
            }

            return false;
        }));
    }

    /**
     * @param list<Node> $nodes
     * @param list<Node> $ancestors
     */
    private function index(array $nodes, string $prefix, array $ancestors): void
    {
        foreach ($nodes as $position => $node) {
            $id = $node->getId() ?? $prefix.'.'.$position;

            if (isset($this->nodes[$id])) {
                $suffix = 2;
                while (isset($this->nodes[$id.'#'.$suffix])) {
                    $suffix++;
                }
                $id .= '#'.$suffix;
            }

            $node->id($id);
            $this->nodes[$id] = $node;
            $this->ancestors[$id] = $ancestors;

            $this->index($node->children(), $id, [...$ancestors, $node]);
        }
    }
}
