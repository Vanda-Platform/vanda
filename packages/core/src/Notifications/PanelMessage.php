<?php

declare(strict_types=1);

namespace Vanda\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Vanda\Layouts\Tone;
use Vanda\Screen\ScreenRegistry;

/**
 * A message in the panel's notification centre (the bell), stored with Laravel's
 * database channel in the same shape as Orchid's DashboardMessage:
 *
 *     $admin->notify(PanelMessage::make('New user')->message("{$user->name} signed up")
 *         ->to(UserEditScreen::class, ['user' => $user])->tone(Tone::Success));
 */
final class PanelMessage extends Notification
{
    use Queueable;

    private ?string $message = null;

    private ?string $action = null;

    private Tone $tone = Tone::Accent;

    private function __construct(private readonly string $title) {}

    public static function make(string $title): self
    {
        return new self($title);
    }

    public function message(?string $message): self
    {
        $this->message = $message;

        return $this;
    }

    /**
     * @param class-string $screen
     * @param array<string, mixed> $parameters
     */
    public function to(string $screen, array $parameters = []): self
    {
        $this->action = app(ScreenRegistry::class)->url($screen, $parameters);

        return $this;
    }

    public function href(string $url): self
    {
        $this->action = $url;

        return $this;
    }

    public function tone(Tone $tone): self
    {
        $this->tone = $tone;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array{title: string, message: ?string, action: ?string, type: string, time: string}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'action' => $this->action,
            'type' => $this->tone->value,
            'time' => now()->toIso8601String(),
        ];
    }
}
