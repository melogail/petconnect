import axios from 'axios';
import { ref, watch } from 'vue';

export function useInertiaInfiniteScroll<T>(
    initialData: any,
    _propName: string,
) {
    const items = ref<T[]>([...initialData.data]);
    const nextUrl = ref<string | null>(
        initialData.meta ? initialData.links.next : initialData.next_page_url,
    );
    const isLoading = ref(false);

    watch(
        () => initialData.data,
        (newItems) => {
            if (Array.isArray(newItems)) {
                items.value = [...newItems];
            }
        },
    );

    watch(
        () => initialData.links?.next ?? initialData.next_page_url,
        (url) => {
            nextUrl.value = url ?? null;
        },
    );

    const loadMore = async () => {
        if (!nextUrl.value || isLoading.value) return;

        isLoading.value = true;
        try {
            const { data: responseData } = await axios.get(nextUrl.value, {
                headers: {
                    Accept: 'application/json',
                },
            });

            items.value = [...items.value, ...responseData.data];
            nextUrl.value = responseData.meta
                ? responseData.links.next
                : responseData.next_page_url;
        } catch (error) {
            console.error('Failed to load more items:', error);
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
