<?php

declare(strict_types=1);

namespace Vanda\Actions;

use Closure;
use Vanda\Access\Authorizer;
use Vanda\Schema\Node;
use Vanda\Schema\SchemaContext;
use Vanda\Screen\ActionCall;

/**
 * Calls a screen method marked with #[Action]:
 *
 *     Button::make('Save')->primary()->action($this->save(...))
 *     Button::make('Delete')->danger()->confirm()->action($this->delete(...), ['user' => $user])
 */
final class Button extends Node
{
    private Variant $variant = Variant::Secondary;

    private ?string $action = null;

    /** @var array<string, mixed> */
    private array $arguments = [];

    private ?string $screen = null;

    private ?string $icon = null;

    private ?string $hotkey = null;

    /** @var array{title: string, text: ?string, confirm: ?string, danger: bool}|null */
    private ?array $confirm = null;

    private bool $bulk = false;

    private bool $block = false;

    public function __construct(private readonly string $label) {}

    public static function make(string $label): self
    {
        return (new self($label))->captureSource();
    }

    public function type(): string
    {
        return 'button';
    }

    public function getId(): ?string
    {
        return $this->id ?? ($this->action !== null ? 'action.'.$this->action : null);
    }

    /**
     * @param Closure $method first-class callable of a screen method: $this->save(...)
     * @param array<string, mixed> $arguments passed to the method by parameter name
     */
    public function action(Closure $method, array $arguments = []): self
    {
        [$target, $name] = ActionCall::target($method);

        if (! Authorizer::allowsAction($target, $name, $arguments)) {
            $this->hidden = true;
        }

        $this->screen = $target::class;
        $this->action = $name;
        $this->arguments = ActionCall::normalizeArguments($arguments);

        return $this;
    }

    public function getAction(): ?string
    {
        return $this->action;
    }

    /**
     * @internal Called by Table::bulkActions(): the action receives `array $ids`
     * of the selected rows, so it must authorize every record itself.
     */
    public function asBulk(): self
    {
        $this->bulk = true;
        $this->arguments = [];

        return $this;
    }

    public function variant(Variant $variant): self
    {
        $this->variant = $variant;

        return $this;
    }

    public function primary(): self
    {
        return $this->variant(Variant::Primary);
    }

    public function ghost(): self
    {
        return $this->variant(Variant::Ghost);
    }

    public function danger(): self
    {
        return $this->variant(Variant::Danger);
    }

    /** Stretch to the full width of its container. */
    public function block(bool $block = true): self
    {
        $this->block = $block;

        return $this;
    }

    /** Icon name from the renderer's icon set, e.g. "save", "trash", "log-in". */
    public function icon(string $icon): self
    {
        $this->icon = $icon;

        return $this;
    }

    /** Keyboard shortcut, e.g. "mod+s" (⌘S on macOS, Ctrl+S elsewhere). */
    public function hotkey(string $hotkey): self
    {
        $this->hotkey = $hotkey;

        return $this;
    }

    /**
     * Ask before running the action. `danger` paints the confirm button red even
     * when the button itself is quiet (e.g. a ghost "Delete" in a table row).
     */
    public function confirm(?string $text = null, ?string $title = null, ?string $confirmLabel = null, bool $danger = false): self
    {
        $this->confirm = [
            'title' => $title ?? $this->label.'?',
            'text' => $text,
            'confirm' => $confirmLabel,
            'danger' => $danger,
        ];

        return $this;
    }

    protected function properties(SchemaContext $context): array
    {
        return [
            'label' => $this->label,
            'variant' => $this->variant->value,
            'icon' => $this->icon,
            'hotkey' => $this->hotkey,
            'block' => $this->block,
            'confirm' => $this->confirm === null ? null : array_filter($this->confirm),
            'action' => $this->action === null ? null : array_filter([
                'name' => $this->action,
                'args' => $this->arguments === [] ? null : $this->arguments,
                'bulk' => $this->bulk,
                'token' => ActionCall::sign((string) $this->screen, $this->action, $this->bulk ? ActionCall::BULK : $this->arguments),
            ]),
        ];
    }
}
