<?php

declare(strict_types=1);

namespace Vanda\Layouts;

use Closure;
use Illuminate\Support\Facades\View;
use Vanda\Schema\Node;
use Vanda\Schema\SchemaContext;
use Vanda\VandaException;

/**
 * HTML from a Blade view among the other nodes (Orchid's Layout::view()):
 *
 *     Layout::view('admin.orders.summary', fn () => ['orders' => $this->orders]),
 *
 * The view is rendered again on every update of the screen, so it follows the state.
 * It is static HTML: a <script> in it does not run, and its forms are not Vanda forms;
 * for something interactive, write a node type. Links to panel pages open without a reload.
 * Your own Tailwind classes are not in Vanda's prebuilt CSS: the content gets Vanda's text
 * styles (headings, lists, tables, links) and its CSS variables (var(--v-accent), ...).
 */
final class BladeView extends Node
{
    private bool $plain = false;

    /**
     * @param array<string, mixed>|Closure(): array<string, mixed> $data
     */
    private function __construct(private readonly string $view, private readonly array|Closure $data) {}

    /**
     * @param array<string, mixed>|Closure(): array<string, mixed> $data
     */
    public static function make(string $view, array|Closure $data = []): self
    {
        return (new self($view, $data))->captureSource();
    }

    public function type(): string
    {
        return 'view';
    }

    /**
     * Without Vanda's text styles, for markup that brings its own.
     */
    public function plain(bool $plain = true): self
    {
        $this->plain = $plain;

        return $this;
    }

    protected function properties(SchemaContext $context): array
    {
        if (! View::exists($this->view)) {
            throw new VandaException("Blade view [{$this->view}] for Layout::view() was not found. Create resources/views/".str_replace('.', '/', $this->view).'.blade.php or fix the name.');
        }

        $data = $this->data instanceof Closure ? ($this->data)() : $this->data;

        return [
            'html' => View::make($this->view, $data)->render(),
            'plain' => $this->plain,
        ];
    }
}
