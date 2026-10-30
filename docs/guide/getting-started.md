# Getting started

## Requirements

| Tool | Version in use |
| --- | --- |
| PHP | 8.4 |
| Composer | 2.x |
| Node / npm | Node 22, npm 10.9 (pinned in `package.json` `packageManager`) |
| Database | MySQL locally (`db:table` works against it); the test suite runs on in-memory SQLite |
| Redis | Optional. Horizon and Reverb are installed; the default `.env.example` uses the `database` queue and `log` broadcaster |

Nova is a private Composer package. The credentials for `nova.laravel.com` must exist in `~/.config/composer/auth.json` before `composer install` will succeed.

## First install

```bash
git clone git@github.com:melogail/petconnect.git sniffpal
cd sniffpal
composer setup
```

`composer setup` runs `composer install`, copies `.env.example` to `.env`, generates the app key, runs migrations, installs npm packages and builds the frontend.

Then seed a realistic dataset:

```bash
php artisan migrate:fresh --seed
```

`DatabaseSeeder` seeds an admin, categories (7) and breeds (82) from `database/data/*.json`, Egyptian cities, users, pets, comments, likes, saves, reviews, reports, conversations and messages.

## Running locally

```bash
composer run dev
```

This wraps `php artisan dev`, which starts the PHP server, queue worker, log tail and Vite together in one terminal UI. The queue worker is not optional: notifications and image conversions are queued, so without it nothing arrives in the bell menu.

Two hosts are in use on the development machine:

- `https://sniffpal.test` through Valet. Valet's nginx config already answers `*.sniffpal.test`, which is what the docs subdomain relies on.
- `http://192.168.1.30:8000` through `artisan serve`, so a phone on the LAN can reach the API while developing the Flutter app.

Set `APP_URL` to whichever one you are using; social login callbacks and generated links depend on it.

## Environment variables that matter

| Variable | Purpose |
| --- | --- |
| `APP_LOCALE` | Default UI language. Supported locales are `en` and `ar`. |
| `NOVA_GUARD=admin`, `NOVA_PASSWORDS=admins` | Nova authenticates against the `admins` table, not `users`. |
| `DOCS_DOMAIN` | Host this documentation answers on. Defaults to `docs.` + the `APP_URL` host. |
| `SESSION_SECURE_COOKIE` | Must be `true` on any HTTPS deployment; `config/session.php` has no default. |
| `VITE_GOOGLE_MAPS_API_KEY` | Map picker and "near me" features. |
| `GOOGLE_CLIENT_ID/SECRET`, `FACEBOOK_CLIENT_ID/SECRET` | Socialite. The redirect registered with the provider must be exactly `APP_URL + /auth/{provider}/callback`. |
| `REVERB_*` | Only needed when `BROADCAST_CONNECTION=reverb`. |

## Admin access

Admins live in the `admins` table and are seeded by `AdminSeeder`. Sign in at `/nova/login`. The same session unlocks this documentation site.
