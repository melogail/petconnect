# Testing & quality

## Stack

- **Pest 5** with the Laravel plugin. Tests live in `tests/Feature` (most) and `tests/Unit`.
- `phpunit.xml` runs on in-memory SQLite with `array` session and cache drivers and `QUEUE_CONNECTION=sync`, so queued jobs and medialibrary conversions run inline.
- Factories exist for every model. Check a factory for named states before building a record by hand.

## Running

```bash
php artisan test --compact                                  # whole suite
php artisan test tests/Feature/Http/DocsSiteTest.php --compact
php artisan test --compact --filter='slug'                 # by test name
vendor/bin/pest tests/Feature/Nova                          # directly
```

Run the narrowest set that covers your change while iterating, then the whole suite before you push.

## What a change needs

Every code change comes with a test for the behaviour and its important failure modes, and nothing beyond those. Feature tests over HTTP are preferred to unit tests of internals.

Conventions worth knowing before writing one:

- **Admin requests** use `actingAs($admin, 'admin')`. A member session is a guest to every admin route.
- **Media** in tests should be `UploadedFile::fake()->image()`, which writes real bytes. `->create()` produces a 0-byte file and the conversion path takes an early exit.
- **Eager-load protection**: a test guarding a `whenLoaded()` relation must assert the key exists in the serialised payload as well as pinning the query count. A dropped eager load lowers the count and leaves the suite green otherwise.
- **Nova actions**: `Exceptions::fake()` cannot observe `report()` inside a Nova action because Nova swaps the exception handler for the request.
- **Query-count pins** are measured under the `array` session driver. A real request pays two or three more queries.
- **Parameterised tests**: break one subject and confirm exactly one dataset case fails, named after that subject.

`.ai/rules/tests.md` holds the measured evidence behind each of these.

## Static checks

| Command | Covers |
| --- | --- |
| `composer lint` / `composer lint:check` | Pint (parallel) |
| `composer types:check` | PHPStan via Larastan |
| `npm run check` | vite-plus lint and format for `resources/js` |
| `npm run types:check` | vue-tsc |

## Continuous integration

`.github/workflows/tests.yml` runs on push: `composer setup` followed by `composer ci:check`. A red run blocks merging; the suite has had intermittent reds in October 2026 and `.ai/rules/tests.md` plus the memory baseline note describe the known causes.
