<?php

declare(strict_types=1);

namespace Vanda\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * The bell: the latest notifications of the signed-in user, and marking them read.
 */
final class NotificationsController
{
    private const LIMIT = 20;

    public function index(): JsonResponse
    {
        return new JsonResponse($this->payload());
    }

    /**
     * POST with "id" marks one notification read, without it marks them all.
     */
    public function read(Request $request): JsonResponse
    {
        $user = Auth::guard(config('vanda.guard'))->user();
        $id = $request->input('id');

        $user->unreadNotifications()
            ->when(is_string($id), static fn ($query) => $query->whereKey($id))
            ->update(['read_at' => now()]);

        return new JsonResponse($this->payload());
    }

    /**
     * @return array{unread: int, items: list<array<string, mixed>>}
     */
    private function payload(): array
    {
        $user = Auth::guard(config('vanda.guard'))->user();

        return [
            'unread' => $user->unreadNotifications()->count(),
            'items' => $user->notifications()->latest()->limit(self::LIMIT)->get()->map(static fn (DatabaseNotification $notification) => array_filter([
                'id' => $notification->id,
                'title' => (string) ($notification->data['title'] ?? Str::headline(class_basename($notification->type))),
                'message' => $notification->data['message'] ?? null,
                'href' => $notification->data['action'] ?? null,
                'tone' => $notification->data['type'] ?? null,
                'when' => $notification->created_at?->diffForHumans(),
                'read' => $notification->read_at !== null,
            ], static fn ($value) => $value !== null))->all(),
        ];
    }
}
