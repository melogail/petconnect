---
paths:
  - 'database/factories/**'
---

# Factories

## Factories: draw locations and street lines from database/data, never Faker's American defaults
PetFactory and UserFactory both use the Database\Seeders\Concerns\ReadsSeedData trait and pick a city from $this->locations(), which reads database/data/locations.json (the trait memoises it in a static, as it does the street list — do not redeclare that static in the factory), so factory-built listings and members sit in the same Egypt/Gulf geography as every seeded row, with the location's own timezone. fake()->city()/state()/country()/timezone() produce "New York, Egypt" style fixtures — do not reintroduce them.

The same goes for the street line: $this->streetAddress() builds "<number> <street>" from database/data/streets.json. Faker's streetAddress() ("46054 Irma Villages") and secondaryAddress() ("Suite 297") contradict the city and country on the same row — PetFactory has buildingDetail() for the second line.

Both factories expose inCity($location) to place a row; UserSeeder and PetSeeder go through it rather than restating the location columns. PetFactory::at($lat, $lng) stays for nearby()/withDistance() tests.

## Suffix generated slugs: Faker's unique pool does not know the seeded ones
CategoryFactory and BreedFactory append `-`.Str::lower(Str::random(6)) to the slug. Faker's unique pool only knows the names it handed out, so without the suffix a factory category can collide with one of the seven slugs CategorySeeder writes from categories.json, and a factory breed created under a *seeded* category (Pet::factory()->for($seededCategory)) can collide with one BreedSeeder writes from breeds.json — the breeds unique index is the composite (category_id, slug), which only helps while the factory mints its own category.

## A DB default the model declares non-nullable belongs in $attributes, not just the factory
A column default only lands in the row, never on the instance, so a non-fillable column with a DB default reads back null in memory until a refresh(). If the model declares it non-nullable (`@property MessageStatus $status`), anything serialising the returned model — API Resource, Inertia prop — ships the lie, and `$model->status->value` fatals.

The primary fix is on the model: `protected $attributes = ['status' => MessageStatus::Sent->value]` on Message and `['status' => ReportStatus::Pending->value]` on Report. Store the **backing value**, not the enum case; the cast resolves it on access. This covers the production path (`Message::create()` / `Report::create()` in an Action), which a factory default never reaches.

MessageFactory also sets status => MessageStatus::Sent, and ReportFactory draws a random ReportStatus. That is belt-and-braces on top of the model default, not the mechanism. Keeping the column out of #[Fillable] costs the factory nothing — Factory wraps construction in Model::unguarded(). Message's pinned_by/pinned_at stay unset because they are genuinely nullable; the pinned() state sets them.

## Wrap an optional()->passthrough() argument in a closure
fake()->optional(0.7)->passthrough($this->records()) evaluates the helper every time and throws the result away 30% of the time. Pass fn (array $attributes) => $this->records() instead: Factory::expandAttributes() resolves closures in the definition after the optional() draw.
