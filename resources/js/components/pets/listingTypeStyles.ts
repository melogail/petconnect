import type { PetListingType } from '@/types';

/**
 * The listing-type pill palette, shared by the feed card badge and the detail
 * page's listing summary so the same type never wears two colours.
 *
 * Each pill is a semantic token under its tonal gradient — two adjacent steps
 * of one hue — and the three hues are the only ones the style permits beside
 * neutrals: the brand violet for adoption, the success emerald for a sale,
 * the warning amber for mating. The token names are exactly what
 * `App\Enums\ListingType::badgeColor()` / `badgeTextColor()` return
 * (`primary`, `success`, `warning` and their `-foreground` pairs), so the
 * backend and this map are two spellings of one decision. The solid token is
 * kept under the gradient as the paint before the image resolves.
 *
 * Contrast, measured at the lighter stop of each gradient so the worst case
 * is known: white on emerald-700 #047857 is 5.2:1; amber-950 #451A03 on
 * amber-400 #FBBF24 is 9.8:1; the brand pair is measured in app.css next to
 * `--gradient-brand`. White on emerald-500/600 fails AA at this 11px size,
 * which is why the green starts at 700.
 *
 * Written over the `PetListingType` union so a fourth type fails to
 * type-check instead of falling through unstyled.
 */
export const LISTING_TYPE_PILL_CLASSES: Record<PetListingType, string> = {
    adoption: 'bg-primary bg-gradient-brand text-primary-foreground',
    sale: 'bg-success bg-gradient-success text-success-foreground',
    mating: 'bg-warning bg-gradient-warning text-warning-foreground',
};
