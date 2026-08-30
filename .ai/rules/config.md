---
paths:
  - config/media-library.php
---

# Config

## force_lazy_loading is deliberately inverted outside production
`force_lazy_loading` defaults to `env('FORCE_MEDIA_LIBRARY_LAZY_LOADING', env('APP_ENV') === 'production')` — the package default is `true`, and we invert it everywhere but production on purpose. Do not "restore" it.

Why: with it on, `InteractsWithMedia::loadMedia()` calls `loadMissing('media')` first — an *explicit* eager load that `Model::preventLazyLoading()` permits. Every `getFirstMediaUrl()` / `getMedia()` on a model without `media` loaded then issues a silent query instead of throwing. That is exactly how a 12-card home feed reached 54 media queries and a category tree 9, both of which had to be found by counting queries by hand rather than by the guardrail that exists to find them.

With it off, the guardrail sees media N+1s: the fix is always to eager load `media` on whatever you iterate (`user.media`, `category.media`, `comments.user.media`), never to turn the flag back on. In production it stays `true` so a missed eager load degrades a page rather than breaking it.
