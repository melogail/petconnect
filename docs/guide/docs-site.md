# This docs site

The documentation is a [VitePress](https://vitepress.dev) site in `docs/`, built to static files and served by the Laravel application itself on its own host. Nothing about it is public.

## How it is served

```
docs.sniffpal.test/anything
  → routes/docs.php        Route::domain(config('docs.domain'))
  → DisallowRobots         adds X-Robots-Tag: noindex, nofollow to every response
  → Nova Authenticate      requires a session on the `admin` guard, else redirects to /nova/login
  → DocsController         maps the path to a file in docs/.vitepress/dist
```

- The build directory sits **outside** `public/`, so the web server never serves it directly; only the guarded route can read it.
- `DocsController` resolves `/`, `/guide/api` and `/guide/api/` to `index.html`, `guide/api.html` and `guide/api/index.html` in turn, refuses anything whose real path leaves the build directory, and falls back to VitePress's `404.html`.
- Content types come from the file extension, so JavaScript modules and CSS load correctly.
- Responses are `Cache-Control: private, no-store`.

### Why Nova's middleware

Nova already authenticates against the `admin` guard and already has a login page. Its `Authenticate` middleware throws an exception that Nova renders as a redirect to `/nova/login` with the intended URL remembered. Nova's routes carry no domain constraint, so they also answer on the docs host and the session cookie is set for it without any `SESSION_DOMAIN` change.

### Why a priority-list entry

Laravel sorts a route's middleware by `$middlewarePriority`, and anything implementing `AuthenticatesRequests` is pulled ahead of unlisted middleware. Without `prependToPriorityList(AuthenticatesRequests::class, DisallowRobots::class)` in `bootstrap/app.php`, a guest's login redirect would leave without the robots header. `tests/Feature/Http/DocsSiteTest.php` pins this.

## Search engines

Three layers say "do not index":

1. `X-Robots-Tag: noindex, nofollow` on every response from the host, redirects included.
2. `<meta name="robots" content="noindex, nofollow">` in every page head (`docs/.vitepress/config.ts`).
3. The pages themselves are unreachable without an admin session.

VitePress's sitemap generation is left off.

## Writing

```bash
npm run docs:dev      # live preview at http://localhost:5173, no auth
npm run docs:build    # writes docs/.vitepress/dist
```

- Pages are Markdown under `docs/`. Add a page to the `sidebar` in `docs/.vitepress/config.ts`.
- `cleanUrls` is on: link to `/guide/testing`, not `/guide/testing.html`.
- Keep claims verifiable against the code. Prefer naming the file where a decision lives over restating it; `.ai/rules` already holds the long form.

## Deploying

The build output is git-ignored. Whatever deploys the app must run `npm run docs:build` after `npm install`, in the same step that runs `npm run build`, and the production `.env` needs:

```
DOCS_DOMAIN=docs.sniffpal.com
```

plus DNS and a TLS certificate for that host pointing at the same application. Without the build directory the route answers 404 with a message naming the command.
