---
paths:
  - 'app/Concerns/**'
---

# Concerns

## Traits live in app/Concerns, not app/Traits
This project has no app/Traits directory; `php artisan make:trait` writes to app/Concerns and the starter kit already used it (PasswordValidationRules). Model behaviour traits ported from the legacy app/Traits live here: HasComments, HasLikes, HasSaves, HasReviews, HasReport. The legacy HasPipeline was dropped — use real pipelines per .ai/rules/pipelines.md.
