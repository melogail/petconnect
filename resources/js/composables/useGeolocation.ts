import { onUnmounted, readonly, ref } from 'vue';

export type GeolocationStatus =
    | 'idle'
    | 'pending'
    | 'granted'
    | 'denied'
    | 'unavailable'
    | 'timeout'
    | 'unsupported';

export type UserCoordinates = {
    latitude: number;
    longitude: number;
};

type StoredLocation = {
    latitude: number;
    longitude: number;
    obtainedAt: number;
};

type StoredPermission = {
    status: Extract<GeolocationStatus, 'denied' | 'unsupported'>;
    storedAt: number;
};

const COORDINATES_KEY = 'petconnect:user-coordinates';
const PERMISSION_KEY = 'petconnect:geolocation-permission';
const COORDINATES_TTL_MS = 5 * 60 * 1000;
const PERMISSION_TTL_MS = 60 * 60 * 1000;

const GEO_OPTIONS: PositionOptions = {
    enableHighAccuracy: true,
    timeout: 10000,
    maximumAge: 300000,
};

function readStoredCoordinates(): UserCoordinates | null {
    try {
        const raw = sessionStorage.getItem(COORDINATES_KEY);
        if (!raw) {
            return null;
        }

        const parsed = JSON.parse(raw) as StoredLocation;
        if (Date.now() - parsed.obtainedAt > COORDINATES_TTL_MS) {
            sessionStorage.removeItem(COORDINATES_KEY);

            return null;
        }

        return {
            latitude: parsed.latitude,
            longitude: parsed.longitude,
        };
    } catch {
        return null;
    }
}

function storeCoordinates(coords: UserCoordinates): void {
    const payload: StoredLocation = {
        ...coords,
        obtainedAt: Date.now(),
    };

    sessionStorage.setItem(COORDINATES_KEY, JSON.stringify(payload));
    sessionStorage.removeItem(PERMISSION_KEY);
}

function readStoredPermission(): StoredPermission['status'] | null {
    try {
        const raw = sessionStorage.getItem(PERMISSION_KEY);
        if (!raw) {
            return null;
        }

        const parsed = JSON.parse(raw) as StoredPermission;
        if (Date.now() - parsed.storedAt > PERMISSION_TTL_MS) {
            sessionStorage.removeItem(PERMISSION_KEY);

            return null;
        }

        return parsed.status;
    } catch {
        return null;
    }
}

function storePermission(
    status: Extract<GeolocationStatus, 'denied' | 'unsupported'>,
): void {
    const payload: StoredPermission = {
        status,
        storedAt: Date.now(),
    };

    sessionStorage.setItem(PERMISSION_KEY, JSON.stringify(payload));
}

function mapGeoError(error: GeolocationPositionError): GeolocationStatus {
    switch (error.code) {
        case error.PERMISSION_DENIED:
            return 'denied';
        case error.TIMEOUT:
            return 'timeout';
        case error.POSITION_UNAVAILABLE:
        default:
            return 'unavailable';
    }
}

/**
 * Obtain the browser location once per short-lived session cache.
 * Does not persist coordinates indefinitely and does not re-prompt after denial.
 */
export function useGeolocation() {
    const status = ref<GeolocationStatus>('idle');
    const coordinates = ref<UserCoordinates | null>(null);
    let cancelled = false;

    const cached = readStoredCoordinates();
    if (cached) {
        coordinates.value = cached;
        status.value = 'granted';
    } else {
        const permission = readStoredPermission();
        if (permission) {
            status.value = permission;
        }
    }

    const requestLocation = (): Promise<UserCoordinates | null> => {
        if (cancelled) {
            return Promise.resolve(null);
        }

        if (coordinates.value) {
            status.value = 'granted';

            return Promise.resolve(coordinates.value);
        }

        const blocked = readStoredPermission();
        if (blocked) {
            status.value = blocked;

            return Promise.resolve(null);
        }

        if (!navigator.geolocation) {
            status.value = 'unsupported';
            storePermission('unsupported');

            return Promise.resolve(null);
        }

        status.value = 'pending';

        return new Promise((resolve) => {
            navigator.geolocation.getCurrentPosition(
                (position) => {
                    if (cancelled) {
                        resolve(null);

                        return;
                    }

                    const next: UserCoordinates = {
                        latitude: position.coords.latitude,
                        longitude: position.coords.longitude,
                    };

                    coordinates.value = next;
                    status.value = 'granted';
                    storeCoordinates(next);
                    resolve(next);
                },
                (error) => {
                    if (cancelled) {
                        resolve(null);

                        return;
                    }

                    const nextStatus = mapGeoError(error);
                    status.value = nextStatus;

                    if (nextStatus === 'denied') {
                        storePermission('denied');
                    }

                    resolve(null);
                },
                GEO_OPTIONS,
            );
        });
    };

    onUnmounted(() => {
        cancelled = true;
    });

    return {
        status: readonly(status),
        coordinates: readonly(coordinates),
        requestLocation,
    };
}
