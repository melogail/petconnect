---
paths:
  - 'app/Http/Controllers/**'
---

# Controllers

## Authorization is $this->authorize() in the controller — never in a Form Request
App\Http\Controllers\Controller uses AuthorizesRequests, and every action calls $this->authorize() against the model policy. Form Requests validate only; do not implement authorize() in them, and do not use Gate::authorize() in controllers.

Why: the legacy app split the same decision three ways — StorePetRequest::authorize() returned true while its controller never called the policy (anyone could publish), UpdatePetRequest checked the policy itself, and PetController::edit checked nothing at all (any verified account could read another owner's vet details, exact coordinates and medications). One convention, one place to audit.
