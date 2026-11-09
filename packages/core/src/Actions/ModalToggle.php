<?php

declare(strict_types=1);

namespace Vanda\Actions;

use Closure;
use ReflectionFunction;
use Vanda\Access\Authorizer;
use Vanda\Schema\Node;
use Vanda\Schema\SchemaContext;
use Vanda\Screen\ActionCall;
use Vanda\Screen\Screen;
use Vanda\VandaException;

/**
 * Opens a Layout::modal(). With ->load() it first calls an #[Action] that fills
 * the modal's form (e.g. for the clicked table row):
 *
 *     ModalToggle::make('Edit')->modal('quickEdit')->load($this->loadQuick(...), ['user' => $user])
 */
final class ModalToggle extends Node
{
    private string $modal = '';

    private Variant $variant = Variant::Secondary;

    private ?string $icon = null;

    /** @var array{name: string, args: array<string, mixed>, screen: string}|null */
    private ?array $load = null;

    public function __construct(private readonly string $label) {}

    public static function make(string $label): self
    {
        return (new self($label))->captureSource();
    }

    public function type(): string
    {
        return 'modaltoggle';
    }

    public function modal(string $id): self
    {
        $this->modal = $id;

        return $this;
    }

    /**
     * @param array<string, mixed> $arguments
     */
    public function load(Closure $method, array $arguments = []): self
    {
        $reflection = new ReflectionFunction($method);
        $target = $reflection->getClosureThis();
        $name = $reflection->getName();

        if (! $target instanceof Screen) {
            throw VandaException::actionTargetNotScreen($name);
        }
        if (! ActionCall::isAction($target::class, $name)) {
            throw VandaException::actionNotMarked($target::class, $name);
        }
        // Before or after ->visible(): a toggle that loads through a forbidden action is never shown.
        $this->forbidden = ! Authorizer::allowsAction($target, $name, $arguments);

        $this->load = ['name' => $name, 'args' => ActionCall::normalizeArguments($arguments), 'screen' => $target::class];

        return $this;
    }

    public function variant(Variant $variant): self
    {
        $this->variant = $variant;

        return $this;
    }

    public function icon(string $icon): self
    {
        $this->icon = $icon;

        return $this;
    }

    protected function properties(SchemaContext $context): array
    {
        return [
            'label' => $this->label,
            'modal' => $this->modal,
            'variant' => $this->variant->value,
            'icon' => $this->icon,
            'action' => $this->load === null ? null : [
                'name' => $this->load['name'],
                'args' => $this->load['args'] === [] ? null : $this->load['args'],
                'token' => ActionCall::sign($this->load['screen'], $this->load['name'], $this->load['args']),
            ],
        ];
    }
}
