/** The address fields the location step can fill in from a coordinate pair. */
export type ReverseGeocodeResult = {
    address: string | null;
    city: string | null;
    state: string | null;
    postalCode: string | null;
    country: string | null;
};

type NominatimResponse = {
    display_name?: string;
    address?: Record<string, string | undefined>;
};

/**
 * Turn a coordinate pair into an address.
 *
 * OpenStreetMap's Nominatim, which is what the legacy form used and needs no
 * key — so "use my location" keeps working in a deployment that has not set
 * `VITE_GOOGLE_MAPS_API_KEY`. Plain `fetch`, not Inertia's client: this is a
 * third-party endpoint, not an application route.
 *
 * Best effort. A failure returns null and the owner types the address, which is
 * the same outcome as never pressing the button.
 */
export async function reverseGeocode(
    lat: number,
    lng: number,
): Promise<ReverseGeocodeResult | null> {
    try {
        const response = await fetch(
            `https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}`,
            { headers: { Accept: 'application/json' } },
        );

        if (!response.ok) {
            return null;
        }

        const body = (await response.json()) as NominatimResponse;
        const address = body.address ?? {};

        return {
            address: body.display_name ?? null,
            city:
                address.city ??
                address.town ??
                address.village ??
                address.municipality ??
                null,
            state: address.state ?? address.region ?? null,
            postalCode: address.postcode ?? null,
            country: address.country ?? null,
        };
    } catch {
        return null;
    }
}
