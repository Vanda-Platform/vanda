<?php

declare(strict_types=1);

namespace Vanda\Navigation;

use Vanda\Access\Authorizer;
use Vanda\Layouts\Avatar;
use Vanda\Screen\ScreenRegistry;

/**
 * A record found by the ⌘K palette:
 *
 *     Panel::search(fn (string $query) => Search::apply(User::query(), ['name', 'email'], $query)->limit(5)->get()
 *         ->map(fn (User $user) => SearchResult::make($user->name)->subtitle($user->email)
 *             ->to(UserEditScreen::class, ['user' => $user])->avatar($user->name)));
 *
 * Results that lead to a screen the user may not open are left out.
 */
final class SearchResult
{
    private ?string $subtitle = null;

    private ?string $href = null;

    private ?string $screen = null;

    private ?string $icon = null;

    private ?string $avatar = null;

    private ?string $image = null;

    private ?string $group = null;

    private function __construct(private readonly string $label) {}

    public static function make(string $label): self
    {
        return new self($label);
    }

    public function subtitle(?string $subtitle): self
    {
        $this->subtitle = $subtitle;

        return $this;
    }

    /**
     * @param class-string $screen
     * @param array<string, mixed> $parameters
     */
    public function to(string $screen, array $parameters = []): self
    {
        $this->screen = $screen;
        $this->href = app(ScreenRegistry::class)->url($screen, $parameters);

        return $this;
    }

    public function href(string $url): self
    {
        $this->href = $url;

        return $this;
    }

    public function icon(string $icon): self
    {
        $this->icon = $icon;

        return $this;
    }

    /**
     * Shows the person's photo, or initials of this name in a coloured circle, instead of an icon.
     */
    public function avatar(string $name, ?string $image = null): self
    {
        $this->avatar = $name;
        $this->image = $image;

        return $this;
    }

    /**
     * Heading the result is listed under; "Records" by default.
     */
    public function group(string $group): self
    {
        $this->group = $group;

        return $this;
    }

    public function isAllowed(): bool
    {
        return $this->href !== null && ($this->screen === null || Authorizer::allowsScreenClass($this->screen));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $initials = $this->avatar === null ? null : Avatar::initials($this->avatar);
        $hue = $this->avatar === null ? null : Avatar::hue($this->avatar);

        return array_filter([
            'label' => $this->label,
            'subtitle' => $this->subtitle,
            'href' => $this->href,
            'icon' => $this->icon,
            'initials' => $initials,
            'hue' => $hue,
            'image' => $this->image,
            'group' => $this->group,
        ], static fn ($value) => $value !== null);
    }
}
