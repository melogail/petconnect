# Feature plans

One file per feature. Each plan records what exists today (inventoried from the code on 2026-10-30), where it lives, how it is guarded, and the open work. Update the plan in the same change that changes the feature.

Format every plan follows: **Purpose · Data · Backend · Routes · Frontend · Guards · Admin · Tests · Open work**.

| Plan | Scope |
| --- | --- |
| [data-model.md](data-model.md) | Every Eloquent model, its table, relations, traits and enums |
| [authentication.md](authentication.md) | Fortify login, register, password reset, email verification, 2FA, passkeys |
| [social-login.md](social-login.md) | Google / Facebook OAuth via Socialite, web + API |
| [api-v1.md](api-v1.md) | Sanctum bearer-token API for the Flutter app |
| [pet-listings.md](pet-listings.md) | Create, edit, show, delete, purge, status toggle, slugs, photos, views |
| [home-feed.md](home-feed.md) | Home feed filters, nearby search, infinite scroll |
| [taxonomy.md](taxonomy.md) | Categories, breeds, seed data, client options |
| [comments.md](comments.md) | Threaded comments on pets |
| [likes.md](likes.md) | Likes on pets, comments and profiles |
| [saves.md](saves.md) | Saved pets (model only, no UI yet) |
| [reviews.md](reviews.md) | Profile reviews with rating |
| [reports-moderation.md](reports-moderation.md) | Reporting comments and reviews, Nova moderation |
| [messaging.md](messaging.md) | Direct conversations, real-time messages, pin/edit/delete |
| [notifications.md](notifications.md) | Database + broadcast notifications, inbox, badges |
| [profiles.md](profiles.md) | Public profile page, contact buttons, profile likes |
| [settings.md](settings.md) | Profile, security, privacy, appearance, account deletion |
| [support-tickets.md](support-tickets.md) | User support tickets answered from Nova |
| [contact-form.md](contact-form.md) | Guest / user contact form on the Support page |
| [help-and-docs.md](help-and-docs.md) | Help centre page and the VitePress docs site |
| [localization.md](localization.md) | EN/AR, RTL, locale switching, translation catalogue |
| [media-library.md](media-library.md) | Spatie media: collections, conversions, decode checks, paths |
| [rate-limiting.md](rate-limiting.md) | Every named throttle and what it protects |
| [nova-admin.md](nova-admin.md) | Nova resources, actions, metrics, dashboards, policies |
