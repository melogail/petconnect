# Data model

Inspect a table with `php artisan db:table <name>`; the Boost `database-schema` tool shows the same. This page is the map, not the schema.

## Entities

```
Admin                            staff; separate guard, Nova + docs

User ─┬─ Pet ─┬─ Category ── Breed
      │       ├─ media (featured + gallery)
      │       ├─ Comment (threaded, polymorphic) ── Like
      │       ├─ Like, Save
      │       └─ Report (polymorphic)
      ├─ Review (on profiles, polymorphic reviewable) ── Report
      ├─ Like (on profiles)
      ├─ Conversation ──< ConversationUser >── User
      │       └─ Message (type, status, pin, edit)
      ├─ Notification (database)
      ├─ SocialAccount (google | facebook)
      ├─ Passkey, two-factor columns, PersonalAccessToken
      └─ media (avatar, owner directory)

Ticket ──< TicketMessage         members' support tickets (header + thread)
ContactMessage                   guests' one-off messages from the Support page
```

## Tables

| Table | Notes |
| --- | --- |
| `users` | `is_active`, `locale`, `bio`, media directory name, 2FA columns. No soft deletes; see cascade rules in [Backend layers](/architecture/backend#deletion-and-cascades). |
| `admins`, `admin_password_reset_tokens` | Staff accounts for the `admin` guard. |
| `categories` | `name`, `name_ar`, `slug`, `description`, `description_ar`. 7 seeded. |
| `breeds` | Per category, bilingual names. 82 seeded from `database/data/breeds.json`. |
| `pets` | 41 columns: identity (`name`, `slug`, `birth_date`, `gender`, `color`, `weight`), litter counts (`quantity`, `male_count`, `female_count`), `listing_type`, `price`, `status`, `views`, location (`address`, `detailed_address`, `city`, `state`, `postal_code`, `country`, `latitude`, `longitude`), health (`health_status`, `vaccinated`, `spayed_neutered`, `special_needs`, `last_vet_visit`, `vaccinations`, `medications`, `allergies` JSON, `vet_name`, `vet_phone`), `traits` and `additional_info` JSON, soft deletes. Lat/long indexed for radius search. |
| `comments` | Polymorphic `commentable` (pet), parent id for replies. |
| `likes` | Polymorphic `likeable` (pet, comment, user). |
| `saves` | User ↔ pet bookmarks. |
| `reviews` | Polymorphic `reviewable` (user profile), rating + sanitised comment. |
| `reports` | Polymorphic `reportable` (user, comment, review), `category`, `reason`, `status`. |
| `conversations` | `type` (direct), `last_message_at`, soft deletes. |
| `conversation_user` | Pivot with a unique (conversation, user) index, read cursors. |
| `messages` | `content`, `type` (text), `status` (sent, …), `pinned_by`/`pinned_at`, `edited_at`. |
| `notifications` | Laravel database notifications, UUID keyed. |
| `media` | spatie/laravel-medialibrary. |
| `social_accounts` | Unique (provider, provider_id); no tokens stored. |
| `passkeys`, `personal_access_tokens` | Passkey credentials; Sanctum tokens. |
| `tickets` | Support ticket headers: `category`, `status`, `last_activity_at`, `closed_at`. |
| `ticket_messages` | The thread under a ticket; `author_type` / `author_id` is a `user` or an `admin`, null once that account is deleted. |
| `contact_messages` | One-off messages from guests (the contact form); no thread, answered by email. |
| `cache`, `jobs`, `sessions` | Framework tables (database drivers locally). |

Morph columns store **aliases** from the morph map (`pet`, `user`, `comment`, `review`), never class names.

## Enums

| Enum | Values |
| --- | --- |
| `ListingType` | adoption, sale, mating |
| `PetStatus` | available, unavailable |
| `PetGender` | male, female (and litter handling via counts) |
| `HealthStatus` | healthy, minor_issues, chronic_condition |
| `Commentable` | pet |
| `Reviewable` | user |
| `Reportable` | user, comment, review |
| `ReportCategory` | abuse, bug, copyright, technical, feedback, other |
| `ReportReason` | per-category reasons |
| `ReportStatus` | pending, reviewed, resolved, rejected |
| `ConversationType` | direct |
| `MessageType` | text |
| `MessageStatus` | sent, … |
| `TicketCategory` | technical_issue, account_issue, listing_issue, missing_category, missing_breed, feedback, other |
| `TicketStatus` | open, answered, closed |
| `SocialProvider` | google, facebook |

Enum keys are TitleCase; backing values are machine-safe snake_case strings and are what the API returns. Labels come from the translation files, and `/api/v1/options` ships them to the mobile app.

## Seed data

`database/data/` holds `categories.json`, `breeds.json`, `locations.json` (22 Egyptian cities) and `streets.json`. Seeders build on these; factories cover every model with states where a test needs one.

## Indexes

`tests/Feature/DatabaseIndexShapeTest.php` and `MysqlIndexPlanTest.php` pin the index shapes that the feed, nearby search and inbox depend on. Adding a query that filters on a new column should come with an index and a pin.
