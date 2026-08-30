---
paths:
  - 'app/Pipelines/**'
---

# Pipelines

## Pipeline step shape
Each step is a class with `handle($context, Closure $next): mixed` that mutates or reads a typed context object and returns `$next($context)`. Steps never know about their neighbours or about HTTP. A step that must abort the flow throws a domain exception rather than returning early with a different type.

Scaffold with `php artisan make:pipeline Payments/Checkout/CreateTransaction` (stub: `stubs/pipeline.stub`).

## An Action runs the pipeline; steps are thin adapters over Actions; shared steps live in {Domain}/Shared
Controllers never build a Pipeline. The entry point is an Action in app/Actions/{Domain}/ that constructs the typed context, runs Illuminate\Pipeline\Pipeline through an explicit step list, and returns the result from then() (see CreatePet, UpdatePet, ListHomeFeedPets).

A step that performs real work delegates to a single-purpose Action of the same name (e.g. Pipelines\Pets\Shared\ResolveCategoryAndBreed -> Actions\Pets\ResolveCategoryAndBreed), so the logic is testable without a pipeline and reusable outside one.

Steps used by more than one flow go in app/Pipelines/{Domain}/Shared/ and type hint a shared abstract context (PetAttributeContext), which is what lets CreatePet and UpdatePet reuse the Normalize* steps unchanged. Flow-specific steps stay in the flow directory.
