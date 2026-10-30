# Help centre and docs site

## Purpose
Two audiences: end users get the in-app Help page with FAQs and quick links; developers get the VitePress docs site served on its own host.

## Help centre
- Page `pages/Help.vue`: intro, quick links (pet profiles, account settings), FAQ accordion (`help.faq_add_pet`, `help.faq_update_pet`), "still need help" linking to Support. Copy in `lang/{en,ar}/help.*`.
- Route: Inertia page, public.
- `pages/Support.vue` hosts the contact form ([contact-form.md](contact-form.md)).

## Docs site
- Source in `docs/` (VitePress): `guide/` (getting started, workflow, testing, docs-site), `architecture/` (overview, backend, frontend, auth, middleware, data-model), `codebase/` (backend, frontend, admin and testing patterns, listing lifecycle), `features/` (listings, messaging, localization, social), `api/` (overview, endpoints).
- Served by `Web/DocsController` on `config('docs.domain')` (default `docs.<app host>`), guarded by `AuthenticateAdmin` (the Nova `admin` guard; `docs.login` redirects to the Nova login page) and `DisallowRobots`, which keeps it out of search engines. Build with `npm run docs:build`.
- Routes: `GET docs.<host>/login`, `GET docs.<host>/{path?}`.

## Tests
`tests/Feature/Http/Controllers/Web/`, `tests/Feature/Http/Middleware/`.

## Open work
- Only two FAQs; grow them from real support tickets.
- Keep `docs/features/*.md` and these plans aligned; the plans are the shorter, agent-facing version.
