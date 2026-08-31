<?php

namespace App\Http\Resources\Notification;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Notifications\DatabaseNotification;

/**
 * One row of the notification inbox.
 *
 * ## The payload ships keys, and the client renders them
 *
 * `.ai/rules/notifications.md` is explicit that a database notification stores
 * translation keys rather than rendered text, because the row outlives the
 * reader's locale — a user who switches to Arabic must see their whole history
 * in Arabic, not sentences frozen in the language they were signed in with when
 * each one arrived. This resource keeps that promise all the way to the wire:
 * it emits `message_key` and `message_replace` and calls `__()` on **neither**.
 *
 * That is the one place it diverges from the legacy NotificationInboxService,
 * which did `__($data['message_key'], $data['message_replace'])` server-side
 * and shipped a finished string. Doing it there is not merely a style
 * preference: it renders in the *request's* locale, which is right for the page
 * being drawn and wrong the moment the client caches the list, and it means a
 * language switch has to refetch every notification rather than re-render.
 *
 * `message_key` may be absent on a row written by something that did not follow
 * the convention, so it falls back to `notifications.default` — a key, not a
 * sentence.
 *
 * ## `time` is an ISO string, not "3 hours ago"
 *
 * The legacy service emitted `diffForHumans()`, which is rendered text with a
 * locale and a reading time baked into it: a list fetched at 09:00 still said
 * "2 minutes ago" at noon. The client gets `created_at` and formats it, which
 * also means the relative wording comes from the same i18n layer as everything
 * else on the page.
 *
 * ## `type` is the payload's own label
 *
 * `like`, `comment`, `review`, `message`, `report` — set by each notification's
 * `toArray()`. It falls back to the notification class's basename, which is
 * what a row written before the convention carries. The client keys its icon
 * and its grouping off this, so it is deliberately not the FQCN in
 * `notifications.type`.
 *
 * `data` is passed through whole under `data` so a client can read the
 * identifiers a particular type carries (`pet_id`, `review_id`, `rate`, the
 * excerpt) without this class having to know every notification's shape.
 *
 * @mixin DatabaseNotification
 */
class NotificationResource extends JsonResource
{
    /**
     * @return array{
     *     id: string,
     *     type: string,
     *     message_key: string,
     *     message_replace: array<string, string>,
     *     url: string|null,
     *     data: array<string, mixed>,
     *     read: bool,
     *     read_at: mixed,
     *     created_at: mixed
     * }
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $data */
        $data = $this->data ?? [];

        return [
            'id' => (string) $this->id,
            'type' => is_string($data['type'] ?? null) ? $data['type'] : class_basename((string) $this->type),
            'message_key' => is_string($data['message_key'] ?? null)
                ? $data['message_key']
                : 'notifications.default',
            'message_replace' => is_array($data['message_replace'] ?? null) ? $data['message_replace'] : [],
            'url' => is_string($data['url'] ?? null) ? $data['url'] : null,
            'data' => $data,
            'read' => $this->read_at !== null,
            'read_at' => $this->read_at,
            'created_at' => $this->created_at,
        ];
    }
}
