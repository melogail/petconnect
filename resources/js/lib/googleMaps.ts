import { importLibrary, setOptions } from '@googlemaps/js-api-loader';

const apiKey = import.meta.env.VITE_GOOGLE_MAPS_API_KEY;

let configured = false;

/**
 * Whether an embedded map can be drawn at all.
 *
 * The key is a Vite variable rather than a backend prop: it is a build-time,
 * publishable, referrer-restricted value, and asking for a shared Inertia prop
 * would have been a backend change for something the bundle can hold itself.
 *
 * The legacy app's "map" was a CSS grid with a pulsing dot and a coordinate
 * readout — it never loaded a mapping library at all, and its own notes list
 * "add a real map library" as outstanding. This is that item, with an honest
 * fallback for a deployment that has not set a key.
 */
export function mapsAvailable(): boolean {
    return typeof apiKey === 'string' && apiKey !== '';
}

/**
 * The Maps and Marker libraries, loaded once per page.
 *
 * `importLibrary` is idempotent — the first call injects the script and every
 * later one resolves against the same load — so there is no module-level
 * promise cache to keep here.
 */
export async function loadMapLibraries(): Promise<{
    maps: google.maps.MapsLibrary;
    marker: google.maps.MarkerLibrary;
}> {
    if (!configured) {
        setOptions({ key: apiKey });
        configured = true;
    }

    const [maps, marker] = await Promise.all([
        importLibrary('maps'),
        importLibrary('marker'),
    ]);

    return { maps, marker };
}

/** A link that opens the pin in Google Maps without needing a key. */
export function externalMapUrl(lat: number, lng: number): string {
    return `https://www.google.com/maps?q=${lat},${lng}`;
}
