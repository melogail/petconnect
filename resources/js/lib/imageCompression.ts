import imageCompression from 'browser-image-compression';

/**
 * Formats the canvas can be re-encoded to without losing what makes them what
 * they are. A GIF is left alone: re-encoding one through a canvas keeps the
 * first frame and throws the animation away, which is a worse outcome than the
 * upload being refused for size.
 */
const COMPRESSIBLE = ['image/jpeg', 'image/png', 'image/webp'];

/**
 * Shrink a picked photo to fit the server's per-file ceiling.
 *
 * `maxKilobytes` is the server's own ceiling, and must be — it reaches here
 * from `photoBounds.max_image_kilobytes`, which `PetPhotoRules::photoBounds()`
 * builds from the same accessor the `max:` rule is built from. Compressing to a
 * hardcoded 512 KB while the environment says 256 KB is a 422 after a full
 * upload on every photo, which is the exact outcome this function exists to
 * prevent. Never call it with a literal.
 *
 * It is smaller than anything a phone camera produces at any setting, so
 * without this the photo step's most common outcome is that 422. This is the
 * "add image compression before upload" item the legacy notes left open.
 *
 * Compression is best effort by design: a file that cannot be re-encoded, or
 * that comes back no smaller, is returned untouched and the backend rule has
 * the last word. The caller still shows `errors.featuredImage` /
 * `errors['images.0']`.
 */
export async function compressListingPhoto(
    file: File,
    maxKilobytes: number,
): Promise<File> {
    if (!COMPRESSIBLE.includes(file.type) || file.size <= maxKilobytes * 1024) {
        return file;
    }

    try {
        const compressed = await imageCompression(file, {
            // A margin under the ceiling: the rule is on the uploaded bytes and
            // the encoder only approximates a target size.
            maxSizeMB: (maxKilobytes * 0.9) / 1024,
            maxWidthOrHeight: 2000,
            useWebWorker: true,
            fileType: file.type,
        });

        return compressed.size < file.size
            ? new File([compressed], file.name, { type: compressed.type })
            : file;
    } catch {
        return file;
    }
}

/** "512 KB" / "1.4 MB" — the size a picker prints next to a file. */
export function formatBytes(bytes: number): string {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    const kilobytes = bytes / 1024;

    return kilobytes < 1024
        ? `${Math.round(kilobytes)} KB`
        : `${(kilobytes / 1024).toFixed(1)} MB`;
}
