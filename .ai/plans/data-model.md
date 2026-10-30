# Data model

## Purpose
Single reference for every Eloquent model in `app/Models`. Feature plans link here instead of repeating schema.

## Models

| Model | Table | Key columns (fillable) | Relations | Traits / notes |
| --- | --- | --- | --- | --- |
| User | users | name, username, bio, email, password, phone, show_phone, whatsapp_enabled, country, state, city, address, lat, lng, timezone, locale. Non-fillable: is_active, email_verified_at, 2FA columns | pets, comments, reports, saves, givenReviews, socialAccounts, conversations (many-to-many via conversation_user) | HasApiTokens, HasLikes, HasReviews, InteractsWithMedia (avatar collection `users`), Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable. Route key = username. Appends `location`. UserObserver assigns a media directory name on creating |
| Admin | admins | name, email, password | – | Separate guard for Nova. Own password reset table |
| Category | categories | name, name_ar, slug, description, description_ar | breeds, pets | Media collection `categories` with `thumb`/`display` conversions |
| Breed | breeds | category_id, name, name_ar, slug, description, description_ar | category, pets | Media collection `breeds` |
| Pet | pets | user_id, category_id, breed_id, name, slug, birth_date, gender, quantity, male_count, female_count, color, weight, description, listing_type, price, status, address, detailed_address, city, state, postal_code, country, latitude, longitude, health_status, vaccinated, spayed_neutered, special_needs, last_vet_visit, vaccinations (json), medications (json), allergies (json), vet_name, vet_phone, traits (json), additional_info (json). Non-fillable: views | user, category, breed; morphMany comments, likes, saves | SoftDeletes. Route key = slug (PetObserver builds `{breed-or-category}-{random8}`). Scopes: available, unavailable, nearby(lat,lng,km), withDistance. Appends `age` (from birth_date). Media collection `pets`, featured photo flagged by custom property `featured` |
| Comment | comments | user_id, content, parent_id, commentable_type, commentable_id | commentable (morph: pet), user, parent, replies; morphMany likes, reports | Threaded. Custom route binding |
| Like | likes | user_id, likeable_type, likeable_id | likeable (pet, comment, user), user | LikeObserver notifies on created |
| Save | saves | user_id, saveable_type, saveable_id | saveable, user | No controller or route uses it yet |
| Review | reviews | user_id, rate, comment, reviewable_type, reviewable_id | reviewable (morph: user), user; morphMany reports | HasReport |
| Report | reports | user_id, reportable_type, reportable_id, category, reason, description, metadata (json) | reportable (comment, review), user | Scopes: withStatus, pending. Status set by Nova |
| Conversation | conversations | type, last_message_at | users (pivot conversation_user), messages, lastMessage | SoftDeletes. Scopes: direct, forParticipant, betweenParticipants. Helpers: hasParticipant, acceptsMessagesFrom, markAsReadFor, otherParticipant, isUnreadFor |
| ConversationUser | conversation_user | conversation_id, user_id, last_read_at | – | Pivot model |
| Message | messages | conversation_id, sender_id, content, type | conversation, sender, pinnedBy | SoftDeletes. Appends is_pinned, is_edited. MessageObserver bumps conversation.last_message_at |
| SocialAccount | social_accounts | provider, provider_id | user | One row per provider login |
| Ticket | tickets | user_id (nullable), name, email, category, subject; status, last_activity_at, closed_at | user, messages | Scope open. isClosed() |
| TicketMessage | ticket_messages | ticket_id, author_type, author_id, body | ticket, author (morph: User or Admin) | isFromSupport() |
| ContactMessage | contact_messages | name, email, subject, message | – | Read in Nova only |

Framework tables: cache, jobs, media (Spatie), notifications, passkeys, personal_access_tokens, password_reset_tokens, admin_password_reset_tokens.

## Enums (`app/Enums`)
- ListingType: adoption, sale, mating
- PetStatus: available, unavailable
- PetGender: male, female, mixed (mixed is for litters)
- HealthStatus: healthy, minor_issues, chronic_condition
- ConversationType: direct · MessageType: text · MessageStatus: sent
- Commentable: pet · Reviewable: user · Reportable: comment, review
- ReportCategory: abuse, bug, copyright, technical, feedback, other
- ReportReason: spam, hate_speech, false_information, violation, inappropriate_content, other
- ReportStatus: pending, reviewed, resolved, rejected
- SocialProvider: google, facebook
- TicketCategory: technical_issue, account_issue, listing_issue, missing_category, missing_breed, feedback, other
- TicketStatus: open, answered, closed

All enums expose `options()` through `App\Concerns\HasOptions` for the client (see [taxonomy.md](taxonomy.md)).

## Contracts and concerns
- Contracts: Likeable, Commentable, Reviewable, Reportable, HasLabel.
- Concerns: HasLikes, HasComments, HasSaves, HasReviews, HasReport, HasOptions, ResolvesMorphTarget, ResolvesRequestUser, plus one `*ValidationRules` trait per feature shared by web and API form requests.

## Seeders and data
`database/seeders` seeds admins, categories, breeds (`database/data/categories.json`, `breeds.json`), users, pets (locations from `locations.json`, `streets.json`), comments, likes, saves, reviews, reports, conversations, messages.

## Open work
- Pets and users are not reportable; only comments and reviews are. Decide whether listings need a report path before launch.
- Save model has no write path (see [saves.md](saves.md)).
- Longer schema narrative lives in `docs/architecture/data-model.md`; keep the two in sync.
