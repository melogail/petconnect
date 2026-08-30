---
paths:
  - 'app/Pipelines/**'
---

# Pipelines

## Pipeline step shape
Each step is a class with `handle($context, Closure $next): mixed` that mutates or reads a typed context object and returns `$next($context)`. Steps never know about their neighbours or about HTTP. A step that must abort the flow throws a domain exception rather than returning early with a different type.

Scaffold with `php artisan make:pipeline Payments/Checkout/CreateTransaction` (stub: `stubs/pipeline.stub`).
