---
paths:
  - 'resources/js/pages/messaging/**'
---

# Messaging

## A 404 from an Inertia POST shows an error modal, not a 404 page (pre-existing, pick up with these pages)
`conversations.store` answers a recipient who cannot be addressed by id — deleted or deactivated, indistinguishably — with a ModelNotFoundException, i.e. a 404. Over an Inertia POST that surfaces as the client-side error modal (the raw error response in an overlay), not as a rendered 404 page, because Inertia only swaps the page component for a valid Inertia response.

This is pre-existing and application-wide (every route-model-bound POST behaves the same), not a regression from removing `Rule::exists('users','id')` from `StoreConversationRequest` — that change only made deactivated and never-issued ids answer alike, closing an enumeration oracle. It is deliberately not fixed in that phase. Recorded against the Phase 4 messaging pages so whoever builds Index.vue / Show.vue handles it there — either a global exception→Inertia error-page mapping or a caught 404 on the "Message" button — rather than treating it as something the backend change broke.
