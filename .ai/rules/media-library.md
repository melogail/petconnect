---
paths:
  - 'app/MediaLibrary/**'
---

# Media Library

## MediaPathGenerator: never touch $media->model, never use class_basename
`getPath()` builds `media/{owner directory}/{model_type}/{model_id}/{media_id}/` from the media row's own columns only.

Two reasons, both measured or bitten before: (1) `$media->model` is an unhydrated `MorphTo` — Laravel does not set the inverse for `morphMany` — so touching it costs one query per media item per URL, and `$model->user` costs a second; a listing page with `Pet::with('media')` issued 4 extra queries for 2 photos. (2) `class_basename($model)` would bake the PHP class name into stored paths; `model_type` already holds the stable morph alias, so renaming a model would orphan every file.

The owner segment comes from the `owner_directory` custom property (`MediaPathGenerator::OWNER_DIRECTORY_PROPERTY`), which whatever attaches media MUST set to the owner's `media_directory_name`. The DB lookup in the generator is a fallback only. The generator is bound `scoped` in `AppServiceProvider::register()` so that fallback can memoise per request; do not memoise statically, it would go stale across a refreshed test database.

The trailing media-id segment is deliberate: without it two same-named uploads on one model overwrite each other, which the legacy generator allowed.
