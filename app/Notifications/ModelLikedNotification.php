<?php

namespace App\Notifications;

use App\Models\Like;
use App\Models\Pet;
use App\Models\User;
use Illuminate\Notifications\Notification;

class ModelLikedNotification extends Notification
{
    /**
     * Create a new notification instance.
     */
    public function __construct(
        public Like $like,
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $this->like->loadMissing(['user', 'likeable']);

        $likerName = $this->like->user?->name ?? __('notifications.someone');
        $likeable = $this->like->likeable;

        $messageKey = match (true) {
            $likeable instanceof Pet => 'notifications.liked_pet',
            $likeable instanceof User => 'notifications.liked_profile',
            default => 'notifications.liked_generic',
        };

        $messageReplace = [
            'name' => $likerName,
            'pet' => $likeable instanceof Pet ? $likeable->name : '',
        ];

        $url = match (true) {
            $likeable instanceof Pet => route('pets.show', $likeable),
            $likeable instanceof User => route('profile.show', $likeable),
            default => null,
        };

        return [
            'like_id' => $this->like->id,
            'liker_id' => $this->like->user_id,
            'liker_name' => $likerName,
            'likeable_type' => $this->like->likeable_type,
            'likeable_id' => $this->like->likeable_id,
            'likeable_name' => $likeable instanceof Pet ? $likeable->name : null,
            'message_key' => $messageKey,
            'message_replace' => $messageReplace,
            'message' => __($messageKey, $messageReplace),
            'url' => $url,
            'type' => 'like',
        ];
    }
}
