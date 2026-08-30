---
paths:
  - 'app/Http/Resources/**'
---

# Resources

## JsonResource::withoutWrapping() is on — single resources are unwrapped, paginators keep data/links/meta
AppServiceProvider::configureDefaults() calls JsonResource::withoutWrapping(). An Inertia prop built from Resource::make($model) therefore arrives as the object itself, not {data: {...}}.

Paginated collections are unaffected: pagination metadata forces the 'data' key back on, so PetCardResource::collection($paginator) still serialises to {data, links, meta}. Frontend code can rely on exactly that split — do not re-add wrapping per resource.
