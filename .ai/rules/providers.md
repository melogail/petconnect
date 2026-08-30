---
paths:
  - 'app/Providers/**'
---

# Providers

## Enforce a morph map before any data is seeded
The morph map lives in `AppServiceProvider::configureMorphMap()` via `Relation::enforceMorphMap([...])`, called from `boot()`. Register every morphable model there before any data is seeded, and keep it updated as polymorphic models are added.

Five polymorphic relations (`likes`, `saves`, `comments`, `reviews`, `reports`) plus `media.model_type` and `notifications.notifiable_type` store these aliases. The legacy app stored fully-qualified class names and called `enforceMorphMap()` nowhere, so any namespace or class rename orphaned every polymorphic row. Fixing it after seeding means a data migration, so the map has to land first. `App\MediaLibrary\MediaPathGenerator` also builds stored file paths out of `media.model_type`, so an alias here is effectively permanent once files exist.

(Kept in sync with the copy in the other file: the trap bites both the model author and the person editing AppServiceProvider, so .ai/rules/models.md and .ai/rules/providers.md carry the same text. Edit both together.)

## Every Nova resource model must be in the morph map (ActionEvent)
Nova's `ActionEvent` writes `actionable_type`, `target_type` and `model_type` as morph values. Because `Relation::enforceMorphMap()` is enforced in `AppServiceProvider::boot()`, any model exposed as a Nova resource that is missing from the map throws `ClassMorphViolationException` at runtime the moment a Nova action runs against it. When you add a Nova resource, add its model to `configureMorphMap()` in the same change — not just the models used in the app's own polymorphic relations.

(Kept in sync with the copy in the other file: .ai/rules/models.md and .ai/rules/providers.md carry the same text. Edit both together.)
