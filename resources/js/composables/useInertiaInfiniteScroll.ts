import axios from 'axios';
import { type Ref, ref, unref, watch } from 'vue';

type PaginatedPayload<T> = {
    data?: T[];
    links?: { next?: string | null } | Array<{ url: string | null }>;
    next_page_url?: string | null;
    meta?: Record<string, unknown>;
};

function resolveNextUrl(payload: PaginatedPayload<unknown>): string | null {
    if (!payload) {
        return null;
    }

    if (payload.meta && payload.links && !Array.isArray(payload.links)) {
        return payload.links.next ?? null;
    }

    return payload.next_page_url ?? null;
}

export function useInertiaInfiniteScroll<T>(
    initialData: Ref<PaginatedPayload<T>> | PaginatedPayload<T>,
    _propName: string,
) {
    const source = () => unref(initialData);

    const items = ref<T[]>([...(source()?.data ?? [])]) as Ref<T[]>;
    const nextUrl = ref<string | null>(resolveNextUrl(source() ?? {}));
    const isLoading = ref(false);

    watch(
        () => source()?.data,
        (newItems) => {
            if (Array.isArray(newItems)) {
                items.value = [...newItems];
            }
        },
        { deep: true },
    );

    watch(
        () => resolveNextUrl(source() ?? {}),
        (url) => {
            nextUrl.value = url;
        },
    );

    const loadMore = async () => {
        if (!nextUrl.value || isLoading.value) {
            return;
        }

        const currentUrl = nextUrl.value;
        isLoading.value = true;

        try {
            const { data: responseData } = await axios.get(currentUrl, {
                headers: {
                    Accept: 'application/json',
                },
            });

            items.value = [...items.value, ...(responseData.data ?? [])];
            nextUrl.value = resolveNextUrl(responseData);
        } catch (error) {
            console.error('Failed to load more items:', error);
            nextUrl.value = null;
        } finally {
            isLoading.value = false;
        }
    };

    return {
        items,
        nextUrl,
        isLoading,
        loadMore,
    };
}
