<?php

declare(strict_types=1);

namespace Vanda\Actions;

use Vanda\Access\Authorizer;
use Vanda\Schema\SchemaContext;
use Vanda\Schema\Node;
use Vanda\Screen\ScreenRegistry;

/**
 * Navigates to a URL or to another screen:
 *
 *     Link::make('Edit')->to(UserEditScreen::class, ['user' => $user])
 */
final class Link extends Node
{
    private ?string $href = null;

    private Variant $variant = Variant::Link;

    private ?string $icon = null;

    private bool $external = false;

    public function __construct(private readonly string $label) {}

    public static function make(string $label): self
    {
        return (new self($label))->captureSource();
    }

    public function type(): string
    {
        return 'link';
    }

    public function href(string $url, bool $external = false): self
    {
        $this->href = $url;
        $this->external = $external;
        $this->forbidden = false;

        return $this;
    }

    /**
     * A link to another screen. A screen the user may not open (#[Can] on its class, checked
     * against a model passed here) hides the link, also after ->visible(true).
     *
     * @param class-string $screen
     * @param array<string, mixed> $parameters
     */
    public function to(string $screen, array $parameters = []): self
    {
        $this->href = app(ScreenRegistry::class)->url($screen, $parameters);
        $this->forbidden = ! Authorizer::allowsScreen($screen, $parameters);

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

    public function secondary(): self
    {
        return $this->variant(Variant::Secondary);
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
            'href' => $this->href,
            'variant' => $this->variant->value,
            'icon' => $this->icon,
            'external' => $this->external,
        ];
    }
}
