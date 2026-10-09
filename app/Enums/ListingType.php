<?php

namespace App\Enums;

use App\Concerns\HasOptions;

enum ListingType: string
{
    use HasOptions;

    case Adoption = 'adoption';
    case Sale = 'sale';
    case Mating = 'mating';

    public function label(): string
    {
        return match ($this) {
            self::Adoption => 'Adoption',
            self::Sale => 'Sale',
            self::Mating => 'Mating',
        };
    }

    /**
     * The design-token name behind this type's badge surface.
     *
     * These are the semantic colour tokens of the "Aubergine & Lavender"
     * style (`resources/css/app.css`): `primary` is the brand violet,
     * `success` the emerald and `warning` the amber — the three hues the
     * style permits. Adoption is the brand default, a sale is a
     * completed-transaction signal, and mating needs the most vetting, so it
     * draws the eye. The frontend pill map in
     * `resources/js/components/pets/listingTypeStyles.ts` is written over the
     * same token names (`bg-primary`, `bg-success`, `bg-warning`, each under
     * its tonal gradient) and is the one that renders; this method is the
     * backend's statement of the same mapping, for Nova or any server-side
     * badge, so the two never disagree about which type is which colour.
     */
    public function badgeColor(): string
    {
        return match ($this) {
            self::Adoption => 'primary',
            self::Sale => 'success',
            self::Mating => 'warning',
        };
    }

    /**
     * The design-token name for text on `badgeColor()`'s surface.
     *
     * Each pairs with its surface at AA or better: white on the emerald
     * gradient (5.2:1 at its lightest stop), `#451A03` on the amber gradient
     * (9.8:1), and `--primary-foreground` on the brand gradient (6.2:1 light,
     * 9.6:1 dark).
     */
    public function badgeTextColor(): string
    {
        return match ($this) {
            self::Adoption => 'primary-foreground',
            self::Sale => 'success-foreground',
            self::Mating => 'warning-foreground',
        };
    }
}
