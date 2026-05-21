import type { AppPageProps, InertiaResource, User } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { computed, type ComputedRef } from 'vue';

function unwrapResource<T>(resource: InertiaResource<T> | null | undefined): T | null {
    if (!resource) {
        return null;
    }

    if (
        typeof resource === 'object' &&
        'data' in resource &&
        resource.data !== undefined
    ) {
        return resource.data;
    }

    return resource as T;
}

export function useAuthUser(): ComputedRef<User | null> {
    const page = usePage<AppPageProps>();

    return computed(() => unwrapResource(page.props.auth?.user));
}
