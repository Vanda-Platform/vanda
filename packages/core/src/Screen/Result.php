<?php

declare(strict_types=1);

namespace Vanda\Screen;

use Vanda\Layouts\Tone;

/**
 * What happens in the browser after an action:
 *
 *     return Result::toast('User saved')->redirect(UserListScreen::class);
 *     return Result::redirectTo(DashboardScreen::class);
 *
 * Returning nothing from an action refreshes the screen.
 */
final class Result
{
    /** @var array{message: string, tone: string}|null */
    private ?array $toast = null;

    private ?string $redirect = null;

    private bool $refresh = true;

    private ?string $openModal = null;

    private function __construct() {}

    public static function toast(string $message, Tone $tone = Tone::Success): self
    {
        return (new self)->withToast($message, $tone);
    }

    /**
     * @param string $target a URL or a screen class
     * @param array<string, mixed> $parameters route parameters for a screen class
     */
    public static function redirectTo(string $target, array $parameters = []): self
    {
        return (new self)->redirect($target, $parameters);
    }

    public static function refresh(): self
    {
        return new self;
    }

    /** Keep the page exactly as it is (no re-render). */
    public static function none(): self
    {
        $result = new self;
        $result->refresh = false;

        return $result;
    }

    public function withToast(string $message, Tone $tone = Tone::Success): self
    {
        $this->toast = ['message' => $message, 'tone' => $tone->value];

        return $this;
    }

    /**
     * @param array<string, mixed> $parameters
     */
    public function redirect(string $target, array $parameters = []): self
    {
        $this->redirect = class_exists($target)
            ? app(ScreenRegistry::class)->url($target, $parameters)
            : $target;

        return $this;
    }

    /**
     * Open a Layout::modal() by id after the screen refreshes, e.g. from a "load" action
     * that filled the modal's form.
     */
    public function openModal(string $id): self
    {
        $this->openModal = $id;

        return $this;
    }

    public function shouldRefresh(): bool
    {
        return $this->refresh && $this->redirect === null;
    }

    /**
     * @return array{toast?: array{message: string, tone: string}, redirect?: string, openModal?: string}
     */
    public function toArray(): array
    {
        return array_filter(['toast' => $this->toast, 'redirect' => $this->redirect, 'openModal' => $this->openModal]);
    }
}
