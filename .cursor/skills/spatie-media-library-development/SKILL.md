---
name: spatie-media-library-development
description: "Use when working with PetConnect media uploads, Spatie Media Library collections, pet featured/gallery images, profile images, media deletion, media URLs in resources, custom media paths, Nova media fields, or image validation/compression."
license: MIT
metadata:
  author: petconnect
---

# Spatie Media Library For PetConnect

## Collections

- `Pet` collection: `pets`.
- `User` collection: `users`.

## Pet Media

- Featured image request key: `featuredImage`.
- Gallery image request key: `images`.
- Deleted media ids request key: `deletedMediaIds`.
- Featured images use custom property `featured => true`.
- Media writes happen after the pet database transaction.

## User Media

- Profile image request key: `profile_image`.
- Profile image updates clear the `users` collection and add a new ULID-named file.

## Paths

`MediaPathGenerator` stores files beneath the owning user's `media_directory_name` when the model is a `User` or has a `user` relationship. System models without an owner (`Category`, `Breed`) store under `media/{model}/{id}/`.

## Validation

- Pet image uploads currently allow jpeg, png, jpg, gif, svg, and webp.
- Pet image max size is 512KB per image.
- Frontend compression helps UX; backend validation remains authoritative.

## Tests

- Use uploaded file fakes and storage/media assertions for upload behavior changes.
