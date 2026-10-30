# Media library

## Purpose
All uploads (pet photos, avatars, taxonomy images) go through Spatie Media Library with project-specific path, decode-safety and cleanup rules.

## Data
`media` table. Collections: `pets` (gallery, one `featured` custom property), `users` (avatar), `categories`, `breeds`. Conversions `thumb` and `display` on every collection.

## Backend (`app/MediaLibrary/`, rules in `.ai/rules/media-library.md` and `.ai/rules/config.md`)
- `MediaPathGenerator` + `OwnerDirectoryResolver`: files live under a per-owner directory named at creation by `MediaOwnerDirectoryObserver` / `UserObserver` (`User::freshMediaDirectoryName()`), so renaming a user never moves files.
- `ImageDecodeVerifier` + pipeline steps `EnsurePhotosAreDecodable`, `EnsureProfileImageIsDecodable` reject files that fail to decode even if the MIME looks right.
- `ConvertibleImageTypes` lists what gets conversions; `TemporaryDirectoryCleaningFileManipulator` cleans temp dirs after conversions.
- Upload rules: `PetPhotoRules`, `TaxonomyImageRules`, avatar size from `bounds.profiles.max_avatar_kilobytes`; photo count/size from `bounds.photos`.
- `EnsureSingleFeaturedPhoto`, `AttachFeaturedImage`, `ReplaceFeaturedImage`, `RemoveDeletedMedia`, `EnsureGalleryCapacity` manage the pet gallery.

## Frontend
Client-side compression with `browser-image-compression` before upload; carousel via embla.

## Admin
Nova resources use `ebess/advanced-nova-media-library` fields for pet, category, breed and user images.

## Tests
`tests/Feature/MediaLibrary/`, `tests/Feature/Observers/`.

## Open work
- Disk is local; pick S3-compatible storage and a CDN before deploying.
- No image moderation (nudity/violence) on upload.
