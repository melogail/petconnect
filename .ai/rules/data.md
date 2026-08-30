---
paths:
  - 'database/data/**'
---

# Data

## database/data holds the checked-in fixtures both seeders and factories read
categories.json, breeds.json, locations.json and streets.json are the source of truth for the demo taxonomy, geography and address lines. They are read through Database\Seeders\Concerns\ReadsSeedData (readSeedData / locations / streets / streetAddress / jitter), which factories use as well as seeders, so a change here moves both seeded rows and factory-built fixtures. locations() and streets() memoise into the trait's own statics ($seedLocations, $seedStreets — per using class), so each seeder and factory decodes a file once per run; callers must not redeclare those statics or re-read the file themselves.
locations.json is a flat list of {city, state, country, postal_code, latitude, longitude, timezone} — keep that shape and keep every entry inside the Egypt/Gulf geography the app has data for. streets.json is a flat list of transliterated street names; addresses are built as "<number> <street>", never from Faker's American streetAddress()/secondaryAddress(). The street list is deliberately region-generic and is not partitioned per city or country — a Cairo street name can legitimately turn up on a Riyadh row. Do not "fix" that by keying streets to locations.
Adding a key means updating the array-shape PHPDoc in ReadsSeedData, PetFactory, UserFactory and the seeders that pass the shape around.
