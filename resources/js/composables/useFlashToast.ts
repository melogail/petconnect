import type { PageProps } from '@inertiajs/core';
import { router } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';

type FlashPayload = {
    success?: string | null;
    error?: string | null;
};

/**
 * Displays session flash messages using Sonner. Safe to call before/without a page layout.
 */
export function showFlashFromPageProps(pageProps: PageProps | undefined): void {
    const flash = pageProps?.flash as FlashPayload | undefined;

    if (!flash) {
        return;
    }

    if (flash.success) {
        toast.success(flash.success, {
            duration: 4500,
        });
    }

    if (flash.error) {
        toast.error(flash.error, {
            duration: 6000,
        });
    }
}

/**
 * Listens for completed Inertia visits and shows flash from the new page props.
 * Must be registered once at app bootstrap (after the plugin is installed).
 */
export function registerInertiaFlashListeners(initialPageProps: PageProps | undefined): void {
    showFlashFromPageProps(initialPageProps);

    router.on('success', (event) => {
        showFlashFromPageProps(event.detail.page.props);
    });
}
