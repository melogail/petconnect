---
paths:
  - 'tests/**'
---

# Tests

## UploadedFile::fake()->create() is a 0-byte file and leaks a medialibrary temp directory per media add
`UploadedFile::fake()->create('avatar.jpg', 10)` does **not** produce a 10 KB file. `FileFactory::create()` returns `new File($name, tmpfile())` and only fakes the reported size (`$file->sizeToReport = $kilobytes * 1024`). The bytes on disk are zero. Only `->image()` and `->createWithContent()` write real content.

Consequence, measured: every `addMedia(UploadedFile::fake()->create(...))` in the suite leaks one directory into `storage/media-library/temp/`. `phpunit.xml` sets `QUEUE_CONNECTION=sync` and `media-library.queue_conversions_by_default` is true, so conversions run inline; `FileManipulator::performConversions()` creates the temp directory, copies the original in, then hits

    if (! file_exists($copiedOriginalFile) || filesize($copiedOriginalFile) === 0) {
        return $this;
    }

and returns **before** `$temporaryDirectory->delete()`. There is no `try`/`finally`, so the same early return also leaks whenever a conversion throws. `Storage::fake()` does not help: the temp path is a raw `storage_path('media-library/temp')`, not a disk, so it is never redirected and the litter lands in the real project tree. Three tests in `LoadPetDetailTest` left 90 directories; the suite had accumulated 7,172 (29 MB), every leaked file exactly 0 bytes.

Seeding is not the cause — no seeder or factory attaches media, and `migrate:fresh --seed` leaves the count unchanged. It is the test suite, and only the test suite.

The upstream leak is spatie/laravel-medialibrary 11.23.5 `Conversions/FileManipulator::performConversions()` and needs a `finally` there. On our side, prefer `UploadedFile::fake()->image('avatar.jpg')` when a test adds media (php8.4-gd is installed, so it writes real bytes and the conversion path completes and cleans up). Where a test genuinely only needs a media row and not a conversion, note that it litters. Do not "fix" this with a blanket `rm` in a test hook that hides a throwing conversion.

## A test protecting a `whenLoaded()` relation must assert the key is present in the payload
`whenLoaded()` drops its key entirely when the relation was never loaded, so a *complete* miss of an eager load is the silent one:

- half-miss (`with('user')` where the payload reaches `user.media`) — `LazyLoadingViolationException`, or the query count goes **up**;
- complete miss (no eager load at all) — no exception, no key, and the query count goes **DOWN**.

Measured twice on this codebase: `ListReviews` fell 5 queries → 3 and `BuildInbox` had the same shape, both with a green suite. So a count assertion *agrees with* the regression it is supposed to catch, and `Model::preventLazyLoading()` never sees a relation nobody touched.

Rule: any test that exists to protect an eager load behind `whenLoaded()` must serialise the payload (`->response()->getContent()`, or the Inertia prop) and assert the key is there — `expect($payload)->toHaveKey('author')` — as well as counting queries. Counting alone covers the half-miss only. Note also that `preventLazyLoading` is off entirely on result sets of 0 or 1 row (see .ai/rules/app.md), so the fixture needs at least two rows of whatever is being iterated.

`grep -rn 'whenLoaded(' app/Http/Resources` is the live list of what this covers; do not work from an enumeration in a rule file.
