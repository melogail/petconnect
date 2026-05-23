---
name: laravel-api-resources
description: "Use when PetConnect sends model data to Inertia pages or JSON responses through Laravel JsonResource classes, including pet cards/details, users/profiles, comments, reviews, messages, conversations, categories, and breeds."
license: MIT
metadata:
  author: petconnect
---

# Laravel API Resources For PetConnect

## Current Pattern

PetConnect uses Laravel `JsonResource` classes to shape many Inertia props, including pets, users, categories, breeds, comments, reviews, conversations, and messages.

Use resources when model data crosses from Laravel into Vue, especially for reusable prop shapes. Preserve existing raw/simple props only when they are already local conventions and changing them would churn the feature.

## Rules

- Keep resources presentation-focused; do not query inside resources except accessing already loaded relations or media URLs.
- Use `whenLoaded()` for relationships when optional.
- Eager load relations in controllers/services before creating resources.
- Do not expose sensitive fields such as passwords, remember tokens, two-factor secrets, or recovery codes.
- Keep field names compatible with existing Vue components.
- Use resources for paginated collections so links/meta are preserved for infinite scroll and pagination.

## PetConnect Shapes

- Pet detail/edit pages depend on `PetDetailResource` fields such as `breed`, `category`, `user`, location fields, health fields, `images`, `feature_image`, and `comments`.
- Home cards may use card-focused pet resources; keep card payloads small and display-ready.
- Messaging uses `ConversationResource`, `ConversationSummaryResource`, and `MessageResource` for inbox/thread data.
- `HandleInertiaRequests` uses `UserResource` for `auth.user`.

## Verification

- Run focused Inertia feature tests when prop names or resource shapes change.
- Run frontend type/build checks if Vue code imports or assumes changed fields.
