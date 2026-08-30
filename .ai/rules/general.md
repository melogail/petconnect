---
paths:
  - composer.json
  - composer.lock
---

# General

## Laravel 13 dependency floors: medialibrary 11.23.5, Nova 5.8, ebess 5.2
Version floors worked out during the Laravel 13 port — do not "correct" them.

- `spatie/laravel-medialibrary`: **v12 does not exist**. 11.23.5 is the first release accepting `illuminate/* ^13.0` (11.20.0 still caps at `^12.0`). Require `^11.23.5`, not `^12`.
- `laravel/nova`: needs **>= 5.8.0** for Laravel 13. Nova 5.0–5.7.x cap at `laravel/framework ^12.1.1`. Nova also requires the `https://nova.laravel.com` composer repository declared in `composer.json`, with licence credentials in `~/.config/composer/auth.json` (never committed).
- `ebess/advanced-nova-media-library`: **5.2** is the first Laravel 13-compatible release. Known future blocker: it still pins `spatie/laravel-medialibrary ^8 – ^11`, so the day medialibrary ships v12 this package will block the upgrade until it widens the constraint.
