<?php

namespace App\Actions\Options;

use App\Concerns\CommentValidationRules;
use App\Concerns\MessageValidationRules;
use App\Concerns\PetPhotoRules;
use App\Concerns\ReviewValidationRules;
use App\Enums\HealthStatus;
use App\Enums\ListingType;
use App\Enums\PetGender;
use App\Enums\PetStatus;
use App\Enums\ReportCategory;
use App\Enums\ReportReason;

/**
 * Everything the mobile client needs to draw a form before it has any data.
 *
 * The web pages receive these as Inertia props on every visit (listing
 * types, report reasons, the comment and photo bounds, the feed filter
 * bounds…). A native client fetches them once at launch instead, so the
 * enum labels, the validation ceilings and the supported locales come from
 * the same accessors the Form Requests validate with — move a config value
 * and both ends move together.
 *
 * @return array<string, mixed>
 */
class BuildClientOptions
{
    use CommentValidationRules, MessageValidationRules {
        CommentValidationRules::maxContentLength insteadof MessageValidationRules;
        MessageValidationRules::maxContentLength as maxMessageLength;
    }
    use PetPhotoRules;
    use ReviewValidationRules;

    /**
     * @return array<string, mixed>
     */
    public function handle(): array
    {
        return [
            'listing_types' => ListingType::options(),
            'statuses' => PetStatus::options(),
            'genders' => PetGender::options(),
            'health_statuses' => HealthStatus::options(),
            'report_categories' => ReportCategory::options(),
            'report_reasons' => ReportReason::options(),
            'bounds' => [
                'filters' => [
                    'default_radius_km' => (float) config('petconnect.nearby.default_radius_km', 20),
                    'min_radius_km' => (float) config('petconnect.nearby.min_radius_km', 1),
                    'max_radius_km' => (float) config('petconnect.nearby.max_radius_km', 100),
                    'max_age_years' => (float) config('petconnect.filters.max_age_years', 15),
                    'default_age_min' => (float) config('petconnect.filters.default_age_min', 0),
                    'default_age_max' => (float) config('petconnect.filters.default_age_max', 15),
                ],
                'photos' => $this->photoBounds(),
                'comments' => $this->commentBounds(),
                'reviews' => $this->reviewBounds(),
                'messages' => ['max_length' => $this->maxMessageLength()],
                'profiles' => [
                    'bio_max_length' => (int) config('petconnect.profiles.bio_max_length', 1000),
                    'max_avatar_kilobytes' => (int) config('petconnect.profiles.max_avatar_kilobytes', 2048),
                ],
            ],
            'locales' => [
                'supported' => config('petconnect.locales.supported', ['en']),
                'rtl' => config('petconnect.locales.rtl', []),
            ],
        ];
    }
}
