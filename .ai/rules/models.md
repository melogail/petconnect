---
paths:
  - 'app/Models/**'
---

# Models

## Enforce a morph map before any data is seeded
Register an explicit morph map with `Relation::enforceMorphMap([...])` in `AppServiceProvider::boot()` **before any data is seeded**, and keep it updated as polymorphic models are added.

The legacy app has five polymorphic relations — `likes`, `saves`, `comments`, `reviews`, `reports` — plus `media.model_type`, all storing fully-qualified class-name strings, and it calls `enforceMorphMap()` nowhere. Any namespace or class rename therefore orphans every existing polymorphic row. Fixing this after seeding means a data migration, so the map has to land first.
