# Project Rules Index

Before planning or editing, read **every** row whose globs match the file's path, not just the first one. The narrow rows come first; the broad `app/**` and `resources/js/**` area rules sit last, so a path like `app/Models/Pet.php` matches both `app/Models/**` and `app/**` and you must read both. Where a narrow rule and a broad one overlap, the narrower one is the specific case.

| Applies to | Rule file |
| --- | --- |
| app/Concerns/** | .ai/rules/concerns.md |
| app/Enums/** | .ai/rules/enums.md |
| app/Http/Controllers/** | .ai/rules/controllers.md |
| app/Http/Resources/** | .ai/rules/resources.md |
| app/MediaLibrary/** | .ai/rules/media-library.md |
| app/Models/** | .ai/rules/models.md |
| app/Notifications/** | .ai/rules/notifications.md |
| app/Observers/** | .ai/rules/observers.md |
| app/Pipelines/** | .ai/rules/pipelines.md |
| app/Providers/** | .ai/rules/providers.md |
| composer.json, composer.lock | .ai/rules/general.md |
| database/data/** | .ai/rules/data.md |
| database/factories/** | .ai/rules/factories.md |
| database/migrations/** | .ai/rules/migrations.md |
| database/seeders/** | .ai/rules/seeders.md |
| app/** | .ai/rules/app.md |
| resources/js/** | .ai/rules/js.md |
