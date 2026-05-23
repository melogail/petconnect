---
name: petconnect-domain-development
description: "Use for PetConnect domain work involving pet listings, create/edit pet wizard, categories, breeds, listing types, profiles, comments, likes, saves, reviews, reports, direct conversations, messages, inbox previews, shared messaging props, home feed, filters, resources, and domain Vue components."
license: MIT
metadata:
  author: petconnect
---

# PetConnect Domain Development

## Pets

- Pet create/edit is an eight-step wizard in Vue.
- Backend accepts wizard-shaped fields and maps them in `PetService`.
- Pet media uses featured and gallery images in collection `pets`.
- `PetDetailResource` is the contract for show/edit pages.
- Home listing uses paginated pet data and infinite scroll.

## Profiles

- Users have profile media, location fields, pets, reviews, and conversations.
- `UserResource` is used for auth/profile data.
- Profile image updates clear and replace collection `users`.

## Messaging

- Direct conversations only.
- Exactly two participants per direct conversation.
- Read state is `conversation_user.last_read_at`.
- `MessageObserver` updates `last_message_at`.
- Use services/policies for all message workflows.

## Comments

- Current commentable enum supports `pet`.
- Replies must belong to the same commentable resource.
- Content processing belongs in `CommentService`.

## Reviews And Reports

- Reviews are polymorphic and use `CreateReviewAction`.
- Reports use enums and must keep frontend/request/action payload names aligned.

## Tests

- Cover guest, owner/non-owner, participant/non-participant, validation, not-found, and soft-delete paths where relevant.
