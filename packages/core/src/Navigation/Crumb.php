<?php

declare(strict_types=1);

namespace Vanda\Navigation;

use Vanda\Screen\ScreenRegistry;

/**
 * One step of the breadcrumbs returned by Screen::breadcrumbs():
 *
 *     return [Crumb::make('Users')->to(UserListScreen::class), Crumb::make($this->user->name)];
 */
final class Crumb
{
    private ?string $href = null;

    private function __construct(private readonly string $label) {}

    public static function make(string $label): self
    {
        return new self($label);
    }

    /**
     * @param class-string $screen
     * @param array<string, mixed> $parameters
     */
    public function to(string $screen, array $parameters = []): self
    {
        $this->href = app(ScreenRegistry::class)->url($screen, $parameters);

        return $this;
    }

    public function href(string $url): self
    {
        $this->href = $url;

        return $this;
    }

    /**
     * @return array{label: string, href?: string}
     */
    public function toArray(): array
    {
        return array_filter(['label' => $this->label, 'href' => $this->href]);
    }
}
