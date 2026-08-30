<?php

namespace App\Notifications;

use App\Models\Like;
use App\Models\Pet;
use App\Models\User;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Route;

/**
 * Database notification telling a user that one of their models was liked.
 */
class ModelLikedNotification extends Notification
{
    public function __construct(
        public Like $like,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * The payload carries the translation key and its replacements, never a
     * rendered string: the notification row outlives the reader's locale, so
     * freezing translated text here would show permanently stale-language
     * history to anyone who switches locale. The client renders
     * `message_key` with `message_replace`.
     *
     * `liker_name` is null only if the liker is gone (likes cascade on user
     * delete, so that is defensive); the client supplies its own localized
     * "someone" for that case rather than the server persisting a key.
     *
     * @return array{
     *     like_id: int,
     *     liker_id: int,
     *     liker_name: string|null,
     *     likeable_type: string,
     *     likeable_id: int,
     *     likeable_name: string|null,
     *     message_key: string,
     *     message_replace: array{name: string, pet: string},
     *     url: string|null,
     *     type: string
     * }
     */
    public function toArray(object $notifiable): array
    {
        $this->like->loadMissing(['user', 'likeable']);

        $likerName = $this->like->user?->name;
        $likeable = $this->like->likeable;

        $messageKey = match (true) {
            $likeable instanceof Pet => 'notifications.liked_pet',
            $likeable instanceof User => 'notifications.liked_profile',
            default => 'notifications.liked_generic',
        };

        $messageReplace = [
            'name' => (string) $likerName,
            'pet' => $likeable instanceof Pet ? $likeable->name : '',
        ];

        return [
            'like_id' => $this->like->id,
            'liker_id' => $this->like->user_id,
            'liker_name' => $likerName,
            'likeable_type' => $this->like->likeable_type,
            'likeable_id' => $this->like->likeable_id,
            'likeable_name' => $likeable instanceof Pet ? $likeable->name : null,
            'message_key' => $messageKey,
            'message_replace' => $messageReplace,
            'url' => $this->likeableUrl(),
            'type' => 'like',
        ];
    }

    /**
     * Deep link to the liked model, or null while the route does not yet exist.
     */
    protected function likeableUrl(): ?string
    {
        $likeable = $this->like->likeable;

        return match (true) {
            $likeable instanceof Pet => $this->routeIfDefined('pets.show', $likeable),
            $likeable instanceof User => $this->routeIfDefined('profile.show', $likeable),
            default => null,
        };
    }

    protected function routeIfDefined(string $name, mixed $parameters): ?string
    {
        return Route::has($name) ? route($name, $parameters) : null;
    }
}
