import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

type TranslationMap = Record<string, string>;

function replacePlaceholders(
    template: string,
    replace: Record<string, string | number> = {},
): string {
    return Object.entries(replace).reduce((result, [key, value]) => {
        return result
            .replaceAll(`:${key}`, String(value))
            .replaceAll(`{${key}}`, String(value));
    }, template);
}

export function useTranslations() {
    const page = usePage();

    const translations = computed(
        () => (page.props.translations as TranslationMap | undefined) ?? {},
    );

    const locale = computed(() => (page.props.locale as string) ?? 'en');
    const dir = computed(() => (page.props.dir as 'ltr' | 'rtl') ?? 'ltr');

    function t(
        key: string,
        replace: Record<string, string | number> = {},
    ): string {
        const template = translations.value[key] ?? key;

        return replacePlaceholders(template, replace);
    }

    return {
        t,
        locale,
        dir,
        translations,
    };
}

export function useLocale() {
    const { locale, dir } = useTranslations();

    return { locale, dir };
}
