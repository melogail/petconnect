<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;

class NotificationInboxService
{
    /**
     * @return array{unread_count: int, items: list<array<string, mixed>>}
     */
    public function sharedPropsFor(User $user): array
    {
        $notifications = $user->notifications()
            ->latest()
            ->limit(20)
            ->get();

        return [
            'unread_count' => $user->unreadNotifications()->count(),
            'items' => $notifications
                ->map(fn (DatabaseNotification $notification) => $this->transform($notification))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function transform(DatabaseNotification $notification): array
    {
        $data = $notification->data;

        $text = isset($data['message_key'])
            ? __($data['message_key'], $data['message_replace'] ?? [])
            : ($data['message'] ?? __('notifications.default'));

        return [
            'id' => $notification->id,
            'type' => $data['type'] ?? class_basename($notification->type),
            'text' => $text,
            'url' => $data['url'] ?? null,
            'time' => $notification->created_at?->diffForHumans() ?? '',
            'read' => $notification->read_at !== null,
            'created_at' => $notification->created_at?->toIso8601String(),
        ];
    }
}
