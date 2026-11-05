<?php

declare(strict_types=1);

namespace Vanda\Navigation;

use Vanda\Access\Authorizer;
use Vanda\Screen\ScreenRegistry;

/**
 * A record found by the ⌘K palette:
 *
 *     Vanda::search(fn (string $query) => User::where('name', 'like', "%{$query}%")->limit(5)->get()
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
     * Shows initials of this name in a coloured circle instead of an icon.
     */
    public function avatar(string $name): self
    {
        $this->avatar = $name;

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
        $initials = null;
        $hue = null;
        if ($this->avatar !== null) {
            $words = preg_split('/\s+/u', trim($this->avatar)) ?: [];
            $initials = mb_strtoupper(implode('', array_map(static fn (string $word) => mb_substr($word, 0, 1), array_slice($words, 0, 2))));
            $hue = crc32($this->avatar) % 6;
        }

        return array_filter([
            'label' => $this->label,
            'subtitle' => $this->subtitle,
            'href' => $this->href,
            'icon' => $this->icon,
            'initials' => $initials,
            'hue' => $hue,
            'group' => $this->group,
        ], static fn ($value) => $value !== null);
    }
}
