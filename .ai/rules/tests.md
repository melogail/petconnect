---
paths:
  - 'tests/**'
---

# Tests

> **"Not observable by this fleet" is a claim to test, not to record as a gap.** The absence of a
> browser test runner from `package.json` is evidence about the tooling, not about whether a
> rendered behaviour can be checked; an unattempted check and an impossible one look identical in
> a report that says neither was tried. The chain that hardened that inference into a standing
> prohibition is in `.ai/rules/general.md`, "Amplification toward inaction: absence of a tool is
> evidence about the tool, not about the task"; the no-dependency method that disproved it — the
> isolated-copy build plus Chrome over CDP — is in `.ai/rules/js.md`, "Browser verification: the
> recipe, the instruments, and their limits". Both are pointers, not copies.

> **Before you trust a green test that involves holding, delaying or intercepting anything, break
> the code it covers and re-run it.** Which stage you intercept at *is* the experiment — a hold at
> the request stage rather than the response stage let a race test pass against both the broken and
> the fixed version. The worked case and the counterfactual recipe are in `.ai/rules/general.md`,
> "A test that passes against known-broken code is worse than no test: the intercept stage IS the
> experiment, so run the counterfactual". This is a pointer, not a copy.

## UploadedFile::fake()->create() is a 0-byte file and leaks a medialibrary temp directory per media add
`UploadedFile::fake()->create('avatar.jpg', 10)` does **not** produce a 10 KB file. `FileFactory::create()` returns `new File($name, tmpfile())` and only fakes the reported size (`$file->sizeToReport = $kilobytes * 1024`). The bytes on disk are zero. Only `->image()` and `->createWithContent()` write real content.

Consequence, measured: every `addMedia(UploadedFile::fake()->create(...))` in the suite leaks one directory into `storage/media-library/temp/`. `phpunit.xml` sets `QUEUE_CONNECTION=sync` and `media-library.queue_conversions_by_default` is true, so conversions run inline; `FileManipulator::performConversions()` creates the temp directory, copies the original in, then hits

    if (! file_exists($copiedOriginalFile) || filesize($copiedOriginalFile) === 0) {
        return $this;
    }

and returns **before** `$temporaryDirectory->delete()`. There is no `try`/`finally`, so the same early return also leaks whenever a conversion throws. `Storage::fake()` does not help: the temp path is a raw `storage_path('media-library/temp')`, not a disk, so it is never redirected and the litter lands in the real project tree.

**Scale, corrected 2026-09-02. The leak is fixed and the current figure is 0.** Two earlier snapshots are recorded here — "7,172 directories (29 MB)", then "9 directories, 480 KB" — and both were real at the time they were taken, of trees nobody had cleaned; neither was a wrong reading and neither was a live crisis. Measured after the fix landed, across ~380 tests including every media-touching suite: **0 directories, 0 files**. Count entries rather than measuring bytes — see the `du` note below.

Seeding is not the cause — no seeder or factory attaches media, and `migrate:fresh --seed` leaves the count unchanged. It was the test suite, and only the test suite.

The upstream leak is spatie/laravel-medialibrary 11.23.5 `Conversions/FileManipulator::performConversions()` and needs a `finally` there. **We supply one**: `App\MediaLibrary\TemporaryDirectoryCleaningFileManipulator` overrides that method alone and is bound over `FileManipulator` in `AppServiceProvider::register()`, so both the 0-byte early return and a throwing conversion now clean up (.ai/rules/media-library.md). Nothing in a test needs to work around it any more. On our side, still prefer `UploadedFile::fake()->image('avatar.jpg')` when a test adds media (php8.4-gd is installed, so it writes real bytes and the conversion path completes and cleans up). Where a test genuinely only needs a media row and not a conversion, note that it litters. Do not "fix" this with a blanket `rm` in a test hook that hides a throwing conversion.

## A test protecting a `whenLoaded()` relation must assert the key is present in the payload
`whenLoaded()` drops its key entirely when the relation was never loaded, so a *complete* miss of an eager load is the silent one:

- half-miss (`with('user')` where the payload reaches `user.media`) — `LazyLoadingViolationException`, or the query count goes **up**;
- complete miss (no eager load at all) — no exception, no key, and the query count goes **DOWN**.

Measured twice on this codebase: `ListReviews` fell 5 queries → 3 and `BuildInbox` fell 7 → 2, both with a green suite, and `Model::preventLazyLoading()` never sees a relation nobody touched.

**Corrected 2026-08-31.** This used to conclude "a count assertion *agrees with* the regression it is supposed to catch". That holds for a **ceiling** — `toBeLessThanOrEqual(7)` passes on 2 — and not for an **equality**: `toBe(7)` fails on 2, and every pin in this suite is an equality. So the pins do catch the complete miss. Do not read the old wording as licence to treat them as worthless, and do not loosen one to a ceiling without replacing what it was catching.

Rule: any test that exists to protect an eager load behind `whenLoaded()` must serialise the payload (`->response()->getContent()`, or the Inertia prop) and assert the key is there — `expect($payload)->toHaveKey('author')` — as well as counting queries. The key assertion is still worth writing next to an equality pin: it states what the test is *for*, it names the regression in the failure message instead of reporting "expected 7, got 2", and it is what survives if the pin is ever relaxed. Counting alone, at a ceiling, covers the half-miss only.

Note also that these pins are measured under this file's `SESSION_DRIVER=array` and `CACHE_STORE=array` (phpunit.xml). A real request on `.env`'s `database` drivers pays 2-3 more queries for `sessions` and `cache` — same feed request, 9 queries under the array driver, 12 for a guest and 11 authenticated under the database one. A pin here is not a whole-request cost. Note also that `preventLazyLoading` is off entirely on result sets of 0 or 1 row (see .ai/rules/app.md), so the fixture needs at least two rows of whatever is being iterated.

`grep -rn 'whenLoaded(' app/Http/Resources` is the live list of what this covers; do not work from an enumeration in a rule file.

## Measuring the media temp-directory leak: the GD hypothesis is falsified and `du` is dishonest
Two dead ends worth not re-deriving, both about `storage/media-library/temp`.

**"Maybe GD is missing, so no conversion ran" is false.** `Illuminate\Http\Testing\FileFactory::image()` throws `LogicException('GD extension is not installed.')` *before* it constructs the file. With GD absent, `->image()` could not have produced an `UploadedFile` at all, so no media row and no temporary directory could ever have been created by it. A missing extension cannot be the cause of the litter. Stop testing that hypothesis.

**`du` measures the wrong thing here.** ext4 never gives directory blocks back, so `du -sh storage/media-library/temp` reported 444K on a directory that had already been emptied. Count entries instead: `find storage/media-library/temp -type f | wc -l`, or `find storage/media-library/temp -maxdepth 1 -mindepth 1 -type d | wc -l`.

Measured after the `finally` fix landed (`App\MediaLibrary\TemporaryDirectoryCleaningFileManipulator`), across ~380 tests including every media-touching suite: **0 directories, 0 files**. The earlier "7,172 / 29 MB" and "9 / 480 KB" snapshots were each accurate when taken — they were uncleaned trees at two different moments, not wrong readings.

## Exceptions::fake() cannot pin a report() made inside a Nova action
Do not write a test that asserts `Exceptions::reported(...)` for a `report($e)` raised inside `app/Nova/Actions/**`, and do not conclude from an empty result that the action fails to report. Nova replaces the container's `ExceptionHandler` with `NovaExceptionHandler` for the duration of a Nova request, so the fake is never the handler that runs. Measured: the danger response asserts fine, `Exceptions::reported()` is empty.

The gap this leaves is real and currently unclosed: five Nova bulk actions promise "The failure has been logged" in their response and no test covers the logging. Removing a `report()` line there keeps the suite green.

`Log::spy()` would observe it and was deliberately declined — it pins how the framework's handler happens to log rather than the behaviour under test. If you reopen this, reopen it with that trade-off in view.

## A parameterised test's protection is only as good as its failure localisation: break one case and count how many fail
**A dataset that fails *every* case when *one* subject breaks tells you something is wrong without telling you where.** That is a materially weaker test than the same assertions written per-case, and the two are indistinguishable while green. **A green dataset says nothing about its localisation** — establishing it requires breaking one subject and counting how many cases fail.

**Measured on `tests/Feature/CommentsDialogPropContractTest.php`.** Counterfactual: delete `'reportCategories' => ReportCategory::options()` from the `Inertia::render()` payload of `App\Http\Controllers\Web\HomeController::index` and re-run the file. Result: **1 failure out of the 5 tests in that file** — the file runs a 3-case dataset (`COMMENTS_DIALOG_HOST_COMPONENTS` = `Home`, `pets/Show`, `profile/Show`) plus two standalone tests — and the failure is the case labelled `with data set "('Home')"`, message **`Property [reportCategories] does not exist. Failed asserting that false is true.`** `pets/Show` and `profile/Show` stayed green. So a single-controller deletion lands on exactly the case named after that controller's page, and the dataset label *is* the diagnosis.

**Provenance, stated because this file's own discipline requires it.** The counterfactual run is **reported, not re-run here**: the pass that recorded this rule was forbidden from touching `app/`, so it could not repeat the deletion. What *was* re-established directly on 2026-09-06: the baseline is green (`php artisan test tests/Feature/CommentsDialogPropContractTest.php --compact` → 5 tests, 5 passed, 51 assertions), the dataset really is those three host components, and the mechanism below was read out of the installed framework source.

**The mechanism it confirms, and the reason the failure is legible rather than a value mismatch.** `Illuminate\Testing\Fluent\Concerns\Matching::where()` opens its body with `$this->has($key)` before it ever reads `prop($key)`. `Has::has()` asserts `Arr::has($prop, $key)` with `sprintf('Property [%s] does not exist.', $this->dotPath($key))` (reported as `Has.php:91`; cited by identifier because the number is not this file's to keep). So a **deleted** prop fails on *existence*, with the missing key named in the message — not on value, and not as a diff of two arrays. `where()` therefore gives you a missing-key assertion for free; you do not need a separate `has()` line to get a named failure for a dropped key.

**Generalise it as a check, not as an anecdote.** For any `->with(...)` dataset guarding N subjects: break one subject, run, and confirm **exactly one** case fails and that it is the one whose label names that subject. If all N fail, the parameterisation is sharing a fixture or a shared assertion the dataset does not actually vary — split it or narrow it. This is the counterfactual discipline of `.ai/rules/general.md`, "A test that passes against known-broken code is worse than no test", pointed at *resolution* rather than at *sensitivity*: that one asks whether a green test can fail at all, this one asks whether its failure names the culprit.

`tests/Feature/CommentsDialogPropContractTest.php` is uncommitted on this tree and belongs to the tester, not to whoever recorded this rule; the measurements above are about it, not licence to edit it.
