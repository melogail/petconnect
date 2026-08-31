import { usePage } from '@inertiajs/vue3';
import { computed, type ComputedRef } from 'vue';
import type { LocaleState } from '@/types/profile';

export type UseLocaleReturn = {
    locale: ComputedRef<LocaleState>;
    /** The BCP 47 tag `Intl` wants — `ar`, `en`, `pt-BR`. */
    tag: ComputedRef<string>;
    isRtl: ComputedRef<boolean>;
};

/**
 * The `locale` shared prop, which `HandleInertiaRequests` puts on every page.
 *
 * `petconnect.locales.rtl` is the one whitelist that decides direction — read
 * `isRtl` rather than comparing against `'ar'` anywhere.
 */
export function useLocale(): UseLocaleReturn {
    const page = usePage();

    const locale = computed<LocaleState>(() => page.props.locale);

    return {
        locale,
        tag: computed(() => locale.value.current.replace('_', '-')),
        isRtl: computed(() => locale.value.direction === 'rtl'),
    };
}
