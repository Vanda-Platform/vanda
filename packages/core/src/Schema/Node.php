<?php

declare(strict_types=1);

namespace Vanda\Schema;

/**
 * Base class of everything a screen draws: fields, layouts, actions, text.
 *
 * A node describes itself as plain data (toSchema) and never renders HTML.
 * The renderer in the browser turns that data into UI.
 */
abstract class Node
{
    /** Set by the service provider: record where nodes are created (dev only). */
    public static bool $captureSource = false;

    protected ?string $id = null;

    protected bool $hidden = false;

    protected ?Expr $visibleWhen = null;

    /** @var list<string> */
    protected array $dependsOn = [];

    protected ?string $source = null;

    /** Short machine name used by the renderer, e.g. "input" or "section". */
    abstract public function type(): string;

    /**
     * Node-specific properties for the IR, without id/type/children.
     *
     * @return array<string, mixed>
     */
    abstract protected function properties(SchemaContext $context): array;

    /**
     * Stable identifier. Fields default to their path, actions to their method.
     */
    public function id(string $id): static
    {
        $this->id = $id;

        return $this;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    /** Show or hide the node on the server. Hidden nodes are not sent to the browser. */
    public function visible(bool $visible = true): static
    {
        $this->hidden = ! $visible;

        return $this;
    }

    /** Show the node only while the expression is true; evaluated in the browser instantly. */
    public function visibleWhen(Expr $condition): static
    {
        $this->visibleWhen = $condition;

        return $this;
    }

    /**
     * Re-resolve this node on the server when any of the paths changes.
     * Only this node travels back to the browser, as a patch.
     */
    public function dependsOn(string ...$paths): static
    {
        $this->dependsOn = array_values(array_unique([...$this->dependsOn, ...$paths]));

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getDependsOn(): array
    {
        return $this->dependsOn;
    }

    public function isVisible(): bool
    {
        return ! $this->hidden;
    }

    public function getVisibleWhen(): ?Expr
    {
        return $this->visibleWhen;
    }

    public function getSource(): ?string
    {
        return $this->source;
    }

    /**
     * @return list<Node>
     */
    public function children(): array
    {
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    public function toSchema(SchemaContext $context): array
    {
        $schema = ['type' => $this->type(), 'id' => $this->id];

        foreach ($this->properties($context) as $key => $value) {
            if ($value !== null && $value !== false && $value !== []) {
                $schema[$key] = $value instanceof Expr ? $value->toArray() : $value;
            }
        }

        if ($this->visibleWhen) {
            $schema['visibleWhen'] = $this->visibleWhen->toArray();
        }

        if ($this->dependsOn !== []) {
            $schema['dependsOn'] = $this->dependsOn;
        }

        if ($this->source !== null) {
            $schema['source'] = $this->source;
        }

        $children = array_values(array_filter(
            array_map(
                static fn (Node $child) => $child->isVisible() ? $child->toSchema($context) : null,
                $this->children(),
            ),
        ));

        if ($children !== []) {
            $schema['children'] = $children;
        }

        return $schema;
    }

    /**
     * Remembers the file and line of the code that created this node so that
     * the IR, `vanda:inspect` and the dev overlay can point to it.
     */
    protected function captureSource(): static
    {
        if (! self::$captureSource) {
            return $this;
        }

        $vandaSrc = dirname(__DIR__);
        foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 12) as $frame) {
            $file = $frame['file'] ?? null;
            if ($file !== null && ! str_starts_with($file, $vandaSrc)) {
                $base = function_exists('base_path') ? base_path().DIRECTORY_SEPARATOR : '';
                $relative = $base !== '' && str_starts_with($file, $base) ? substr($file, strlen($base)) : $file;
                $this->source = $relative.':'.($frame['line'] ?? 0);
                break;
            }
        }

        return $this;
    }
}
