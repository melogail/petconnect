# Nova admin

## Purpose
Back-office on `nova.<host>` for the `admins` guard: moderate content, manage taxonomy, answer tickets, read contact messages and watch growth metrics.

## Data
`admins`, `admin_password_reset_tokens`; `AdminSeeder` creates the first admin.

## Resources (`app/Nova/`)
Admin, User, Pet, Category, Breed, Comment, Review, Report, Ticket, TicketMessage, ContactMessage. Base class `app/Nova/Resource.php`. Rules: `.ai/rules/nova.md`, `nova-actions.md`, `nova-policies.md`.

## Actions (`app/Nova/Actions/`)
ChangeReportStatus, PurgeOrphanedReports, ChangeTicketStatus, ReplyToTicket, DeactivateUser, ReactivateUser, DeleteUserAccount, PurgePetListing, DeleteCommentThread, DeleteReview, DeleteCategory. Destructive actions reuse the application purge pipelines rather than raw deletes.

## Filters, metrics, dashboards
- Filters: EnumFilter base, ReportStatusFilter, ReportCategoryFilter, ReportReasonFilter.
- Metrics: TotalUsers, NewUsers, AverageUsers, NewPets, PendingReports, ReportsByStatus, ReportsFiled.
- Dashboards: `Main` (growth) and `Moderation` (reports).

## Policies (`app/Nova/Policies/`)
One per resource; admins are authorised through the `admin` guard in `NovaServiceProvider`. Horizon dashboard access is gated in `HorizonServiceProvider`.

## Tests
`tests/Feature/Nova/{Actions,Metrics,Policies}/`.

## Open work
- Single admin role; no permission tiers (moderator vs. owner).
- No audit log of admin actions.
