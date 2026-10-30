---
layout: home

hero:
  name: SniffPal
  text: Engineering documentation
  tagline: Internal reference for the web app, the admin panel and the v1 API. Admin access only.
  actions:
    - theme: brand
      text: Getting started
      link: /guide/getting-started
    - theme: alt
      text: Architecture
      link: /architecture/overview
    - theme: alt
      text: API v1
      link: /api/overview

features:
  - title: Laravel 13 · Inertia v3 · Vue 3
    details: Thin controllers, Actions and Pipelines on the server; Vue pages rendered through Inertia with Wayfinder-typed routes on the client.
  - title: Pet marketplace
    details: Adoption, sale and mating listings with breed taxonomy, structured health data, geo "near me" search, likes, saves, comments, reviews and real-time messaging.
  - title: Two audiences, two guards
    details: Members sign in through Fortify (passwords, 2FA, passkeys, Google/Facebook). Admins use the separate `admin` guard that also gates Nova and this site.
  - title: One API for the mobile app
    details: Sanctum bearer tokens, slug-addressed listings, an options endpoint that ships validation limits and enum labels so the Flutter app mirrors the web wizard.
---

## Where things live

| Looking for | Go to |
| --- | --- |
| Install, run, seed | [Getting started](/guide/getting-started) |
| Branch, format, test, review | [Daily workflow](/guide/workflow) · [Testing & quality](/guide/testing) |
| How a request flows | [Request pipeline](/architecture/middleware) |
| The code, hop by hop | [A listing, end to end](/codebase/listing-lifecycle) |
| Who can do what | [Authentication & admin](/architecture/auth) |
| Tables and relations | [Data model](/architecture/data-model) |
| Mobile app contract | [API endpoints](/api/endpoints) |
| Settled decisions and traps | `.ai/rules/*.md` in the repository, indexed by `.ai/rules/index.md` |

::: warning Source of truth
These pages describe the codebase as of October 2026. Where a page and the code disagree, the code wins. The committed rule files under `.ai/rules` hold the detailed, dated reasoning behind most decisions summarised here.
:::
