<?php

namespace App\MediaLibrary;

/**
 * The image formats this application accepts, in one place.
 *
 * The list is not "images" — it is the subset of images the conversion driver
 * can actually read. `config('media-library.image_driver')` is `gd`, and GD
 * decodes JPEG, PNG, GIF and WebP and nothing else. Laravel's bare `image`
 * rule is wider than that: in this version it admits `bmp`, `heic` and `heif`
 * as well, so a resource validating with `image` alone accepts a file that
 * every conversion then fails to produce a derivative for — and because
 * `getFirstMediaUrl()` silently falls back to the original when a conversion is
 * missing, the raw multi-megabyte upload is served where a 160px crop was
 * intended, with nothing looking broken. `App\Nova\Breed` and
 * `App\Nova\Category` both drifted that way.
 *
 * Two spellings of the same list, because the two consumers want different
 * forms: `mimesRule()` for a validator (Laravel maps the extension to the mime
 * type itself, which is why `jpg` and `jpeg` both appear), and `MIME_TYPES` for
 * `MediaCollection::acceptsMimeTypes()`, which compares the sniffed mime type
 * of the file. Keep them in step.
 *
 * Every file rule in the application is built from here —
 * `App\Concerns\PetPhotoRules::photoFileRules()`,
 * `App\Concerns\ProfileValidationRules::avatarFileRules()` and
 * `App\Concerns\TaxonomyImageRules::taxonomyImageFileRules()` — so the member
 * form, the Nova form and the media collection cannot disagree about what an
 * image is.
 */
class ConvertibleImageTypes
{
    /**
     * Extensions for a `mimes:` rule.
     *
     * @var list<string>
     */
    public const EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    /**
     * The same formats as mime types, for acceptsMimeTypes().
     *
     * @var list<string>
     */
    public const MIME_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    /**
     * The `mimes:` rule covering every format a conversion can read.
     */
    public static function mimesRule(): string
    {
        return 'mimes:'.implode(',', self::EXTENSIONS);
    }
}
