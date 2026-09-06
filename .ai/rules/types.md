---
paths:
  - 'resources/js/types/**'
---

# Types

## Auth.user is typed non-nullable and is null for guests
HandleInertiaRequests shares $request->user(), so auth.user is null on every public page. The type says User, not User | null, deliberately: widening it cascades into ~15 scaffold pages that read page.props.auth.user.name directly, none of them guest-reachable. Guard auth.user at runtime in anything a guest can reach — the five guest routes (Home, pets/Show, profile/Show, Help, Support) and everything they render, **including `PublicLayout` and `PublicHeader`, which since 2026-09-06 wrap every non-auth page rather than only those five** (the starter kit's sidebar shell is gone; see `app.ts`). Being on `PublicLayout` no longer tells you a page is guest-reachable — the route does. vue-tsc will not catch it. Narrow to User | null when there is room for the scaffold-page cleanup.
