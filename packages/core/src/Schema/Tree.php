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
     * Fields a button validates: inside a modal, only that modal's fields; a button given to
     * a section's or card's ->commands(), only that block's fields; anywhere else, every
     * field that is not inside a modal.
     *
     * @return list<Field>
     */
    public function fieldsInScope(?string $triggerId): array
    {
        $trigger = $this->nodes[(string) $triggerId] ?? null;
        $scope = null;
        foreach (array_reverse($this->ancestors[(string) $triggerId] ?? []) as $ancestor) {
            if ($trigger !== null && $ancestor instanceof ValidationScope && $ancestor->scopes($trigger)) {
                $scope = $ancestor;
                break;
            }
        }

        return array_values(array_filter($this->fields(), function (Field $field) use ($scope): bool {
            $ancestors = $this->ancestors[(string) $field->getId()] ?? [];

            if ($scope !== null) {
                return in_array($scope, $ancestors, true);
            }

            return array_filter($ancestors, static fn (Node $node) => $node instanceof Modal) === [];
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
     * Sent to the browser at all: the node and every block around it are visible on the server
     * (->visible(), #[Can]). Unlike isShown(), browser conditions (visibleWhen) are not read.
     */
    public function isRendered(Node $node): bool
    {
        foreach ([...($this->ancestors[(string) $node->getId()] ?? []), $node] as $current) {
            if (! $current->isVisible()) {
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
