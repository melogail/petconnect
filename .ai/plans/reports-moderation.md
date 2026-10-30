# Reports and moderation

## Purpose
Users flag comments and reviews; admins triage them in Nova.

## Data
`reports` (user_id, reportable_type/id, category, reason, description, metadata, status). Reportables: Comment, Review. Enums ReportCategory, ReportReason, ReportStatus (pending → reviewed/resolved/rejected).

## Backend
- `app/Actions/Reports/CreateReport.php` runs SubmitReport: ResolveReportable → EnsureNotSelfReport → EnsureNotAlreadyReported → PersistReport → NotifyModerators.
- `ReportPolicy`, `ReportValidationRules`, requests in `app/Http/Requests/Report/`.
- `ReportFiledNotification` (database) to admins.
- `HasReport` concern on Comment and Review exposes `reportSubjects()` (who is being reported).

## Routes
`POST reports/{type}/{id}` (web) and `POST api/v1/reports/{type}/{id}`. Auth required.

## Frontend
Report dialog on comment and review items; categories/reasons come from the shared `options` prop.

## Guards
Throttle `reports` (5/min). Duplicate and self reports are rejected in the pipeline.

## Admin
Nova `Report` resource with filters (`ReportStatusFilter`, `ReportCategoryFilter`, `ReportReasonFilter`), actions `ChangeReportStatus`, `PurgeOrphanedReports`; `Moderation` dashboard with metrics `PendingReports`, `ReportsByStatus`, `ReportsFiled`.

## Tests
`tests/Feature/Actions/Reports/`, `tests/Feature/Nova/Actions/`, `tests/Feature/Nova/Metrics/`.

## Open work
- Pets and users cannot be reported. For a marketplace this is the most likely abuse surface (scam listings); add `Reportable::Pet` and `Reportable::User` before launch.
- No written listing/welfare policy exists for moderators to apply.
