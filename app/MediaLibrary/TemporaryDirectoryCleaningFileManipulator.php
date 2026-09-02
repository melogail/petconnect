<?php

namespace App\MediaLibrary;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\Conversions\ConversionCollection;
use Spatie\MediaLibrary\Conversions\FileManipulator;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * The `finally` that spatie/laravel-medialibrary 11.23.5 does not have.
 *
 * `Conversions\FileManipulator::performConversions()` creates a temporary
 * directory, copies the original into it, and deletes it on the very last line
 * of the method with nothing wrapping the work in between:
 *
 *     $temporaryDirectory = TemporaryDirectory::create();
 *     $copiedOriginalFile = app(Filesystem::class)->copyFromMediaLibrary(...);
 *
 *     if (! file_exists($copiedOriginalFile) || filesize($copiedOriginalFile) === 0) {
 *         return $this;              // <- leaks
 *     }
 *
 *     $conversions->each(...);       // <- throws, leaks
 *
 *     $temporaryDirectory->delete();
 *
 * Both branches strand a directory under `storage/media-library/temp`: the
 * 0-byte early return (every `UploadedFile::fake()->create()` in the suite —
 * see .ai/rules/tests.md) and any throw out of the conversion loop (a file that
 * sniffs as an image but that GD cannot decode raises
 * `Spatie\Image\Exceptions\CouldNotLoadImage`). One crafted upload is a leaked
 * directory; a loop is unbounded inode growth, and `PerformConversionsJob`
 * calls the same method, so every queued retry leaks again.
 *
 * ## Why a subclass and not a patch
 *
 * The package resolves the manipulator out of the container every time —
 * `MediaCollections\Filesystem`, `Models\Observers\MediaObserver` and
 * `Conversions\Jobs\PerformConversionsJob` all take it from `app()` or from
 * constructor injection — so binding this subclass in
 * `AppServiceProvider::register()` covers the inline path, the queued path and
 * the `media-library:regenerate` command at once. A vendor patch file would
 * cover the same ground and would then have to survive every `composer update`.
 *
 * ## Why the temporary root is scoped rather than diffed
 *
 * `performConversions()` owns its temporary directory: the handle is a local
 * variable and `Support\TemporaryDirectory::create()` is a hard static call, so
 * an override cannot be handed the path and cannot subclass its way to it. The
 * obvious alternative — list the temporary root before and after and delete
 * whatever is new — is wrong in production: a concurrent request's live
 * temporary directory is "new" from this call's point of view and would be
 * deleted out from under a conversion that is still running.
 *
 * So the root is narrowed for the duration of the call instead.
 * `Support\TemporaryDirectory` reads `media-library.temporary_directory_path`
 * on every `create()`, and the configuration repository is per process, so
 * pointing it at a private subdirectory makes everything the parent creates —
 * including the responsive-image generator's own temporary directories —
 * land somewhere this call can delete wholesale, with no cross-process reach.
 * The previous value is restored before the delete, so nothing observes the
 * narrowed root afterwards, and the deferred conversions the parent schedules
 * with `defer()` run later through this same override and scope themselves.
 *
 * The delete stays best effort: a directory that cannot be removed must not
 * turn a successful upload into a failure, and must never mask the exception
 * the conversion threw — `File::deleteDirectory()` returns false rather than
 * throwing, so the `finally` cannot swallow it.
 */
class TemporaryDirectoryCleaningFileManipulator extends FileManipulator
{
    /**
     * The configuration key `Support\TemporaryDirectory::create()` reads.
     */
    private const TEMPORARY_PATH_KEY = 'media-library.temporary_directory_path';

    /**
     * Run the package's conversions, then delete what they left behind.
     *
     * @throws \Throwable Whatever the conversion loop threw, unchanged.
     */
    public function performConversions(
        ConversionCollection $conversions,
        Media $media,
        bool $onlyMissing = false
    ): FileManipulator {
        $configuredPath = config(self::TEMPORARY_PATH_KEY);
        $scopedPath = ($configuredPath ?? storage_path('media-library/temp'))
            .DIRECTORY_SEPARATOR.Str::random(32);

        config([self::TEMPORARY_PATH_KEY => $scopedPath]);

        try {
            return parent::performConversions($conversions, $media, $onlyMissing);
        } finally {
            config([self::TEMPORARY_PATH_KEY => $configuredPath]);

            File::deleteDirectory($scopedPath);
        }
    }
}
