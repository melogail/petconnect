# Notifications

## Purpose
In-app inbox plus real-time toasts for likes, comments, reviews, messages and ticket replies.

## Data
`notifications` table (Laravel database channel). Broadcast over Reverb private channel `App.Models.User.{id}`.

## Backend
- Notification classes (`app/Notifications/`): ModelLikedNotification (db + broadcast), ModelCommentedNotification (db + broadcast), ModelReviewedNotification (db), NewMessageNotification (db + broadcast), ReportFiledNotification (db, to admins), TicketRepliedNotification (db + mail), VerifyEmailNotification (mail). All queued; see architecture review memory for why.
- Actions (`app/Actions/Notifications/`): BuildNotificationInbox, CountUnreadNotifications, MarkNotificationAsRead, MarkAllNotificationsAsRead, DeleteAllNotifications.
- Resource `app/Http/Resources/Notification/`. Controllers `Web/NotificationController`, `Api/V1/NotificationController`, `Api/V1/BadgeController`.

## Routes
Web and API: `GET notifications`, `POST notifications/{notification}/read`, `POST notifications/read-all`, `DELETE notifications`. API: `GET me/badges`.

## Frontend
`components/notifications/NotificationMessage.vue` + `notificationMessage.ts` (maps type → localized text), bell/unread badge in the shell, toasts via `vue-sonner`, `useEchoNotification` from echo-vue.

## Guards
Throttle `inbox-actions` (60/min). Users only see their own rows.

## Tests
`tests/Feature/Notifications/`, `tests/Feature/Http/Controllers/Web/`.

## Open work
- No push notifications for the mobile app (FCM/APNs); the app polls `me/badges`.
- No email digest for unread messages.
