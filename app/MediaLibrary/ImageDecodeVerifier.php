<?php

namespace App\MediaLibrary;

use Illuminate\Http\UploadedFile;
use Spatie\Image\Image;
use Throwable;

/**
 * Can the conversion driver actually read this upload?
 *
 * `image` and `mimes:` both decide on the *header*: `finfo` sniffs the magic
 * bytes and Laravel compares the result against the allowed list. A file that
 * opens with a genuine JPEG SOI marker and a JFIF APP0 block and then carries
 * nothing but padding passes every one of them — and fails the moment GD is
 * asked for the pixels, which is inside the conversion, which is after the
 * media row and the stored original are committed. The user gets a 500, the
 * listing gets a row whose `display` URL points at a file that was never
 * written (`Media::getUrl()` does not fall back), and the temporary directory
 * the conversion was using is stranded (see
 * TemporaryDirectoryCleaningFileManipulator).
 *
 * So the decode is attempted up front, through the same loader the conversion
 * uses — `Spatie\Image\Image` on `media-library.image_driver` — rather than
 * through a hand-rolled `getimagesize()` check that could disagree with it.
 * That makes this the one thing in the upload path that is not a header check.
 *
 * It is a collaborator rather than a validation rule because the driver name is
 * media configuration, and because the callers are pipeline steps, which never
 * read `config()` themselves (.ai/rules/pipelines.md).
 */
class ImageDecodeVerifier
{
    /**
     * True when the driver loaded the file, false for any reason it did not.
     *
     * Every failure is a refusal: `Throwable` is caught deliberately rather
     * than the driver's own `CouldNotLoadImage`, because which exception a
     * driver raises for an unreadable file is an implementation detail of that
     * driver, and an upload that cannot be loaded is unusable whichever one it
     * is. Nothing here inspects or reports the reason — the caller turns a
     * false into the field error the user can act on.
     */
    public function canDecode(UploadedFile $file): bool
    {
        $path = $file->getRealPath();

        if ($path === false || ! is_file($path)) {
            return false;
        }

        try {
            Image::useImageDriver(config('media-library.image_driver'))->loadFile($path);
        } catch (Throwable) {
            return false;
        }

        return true;
    }
}
