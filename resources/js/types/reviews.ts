import type { SelectOption } from './pets';
import type { UserSummary } from './users';

/**
 * One review — `App\Http\Resources\Review\ReviewResource`.
 *
 * `author` is `ReviewAuthorResource`, which is `UserSummaryResource` under
 * another name, and is absent (not null) when the loader did not eager load
 * `user.media`.
 */
export type Review = {
    id: number;
    rate: number;
    comment: string | null;
    author?: UserSummary;
    is_author: boolean;
    has_reported: boolean;
    can_edit: boolean;
    can_delete: boolean;
    created_at: string;
    updated_at: string;
};

/**
 * The review scale and comment ceiling — the `reviewBounds` prop on
 * `profile.show`.
 *
 * Built by `App\Concerns\ReviewValidationRules::reviewBounds()` from the same
 * accessors the `min:` / `max:` rules are built from, so the star widget and
 * the validator cannot disagree. Nothing may hardcode 5 again.
 */
export type ReviewBounds = {
    min_rate: number;
    max_rate: number;
    max_comment_length: number;
};

export type ReportCategory =
    | 'abuse'
    | 'bug'
    | 'copyright'
    | 'technical'
    | 'feedback'
    | 'other';

export type ReportReason =
    | 'spam'
    | 'hate_speech'
    | 'false_information'
    | 'violation'
    | 'inappropriate_content'
    | 'other';

/**
 * The two option lists a report dialog needs.
 *
 * They are props on the page that hosts the dialog (`profile.show`,
 * `pets.show`), not a separate endpoint, so a page with a report control has to
 * accept and forward them.
 */
export type ReportOptions = {
    reportCategories: SelectOption<ReportCategory>[];
    reportReasons: SelectOption<ReportReason>[];
};

/** What `reports.store` and `reviews.store` take as their morph-type segment. */
export type ReportableType = 'comment' | 'review';
export type ReviewableType = 'user';
