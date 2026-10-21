/**
 * The address on the support page.
 *
 * It is a **frontend constant, not a prop**. `support` and `help` are
 * `Route::inertia()` routes with no controller and no props, and routes/web.php
 * spells out why one cannot simply be added: `Route::inertia()` stores its
 * arguments as route defaults, which `route:cache` serialises, so a `config()`
 * read at registration time would be frozen into the cached route file. Adding
 * one properly means a controller or a shared prop, and neither is worth it for
 * a single mailto.
 *
 * It lives here rather than inline in `pages/Support.vue` so that it is one
 * grep away and there is exactly one of it the day a footer, an error page or a
 * "report a problem" link wants the same address.
 *
 * The contact form itself (`components/support/ContactDialog.vue`) does not
 * need this: it posts to `contact.store` and the address only matters to the
 * back office. This is the `mailto:` fallback shown under it.
 *
 * When it does become configurable — a per-environment address, an office-hours
 * line — it becomes a shared prop on `HandleInertiaRequests` and this file
 * goes away. Until then, changing the address is changing this line.
 */
export const SUPPORT_EMAIL = 'support@petconnect.com';

/** The `href` of a "mail the support team" link. */
export const SUPPORT_MAILTO = `mailto:${SUPPORT_EMAIL}`;
