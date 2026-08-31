import { router } from '@inertiajs/vue3';
import type { LocaleState } from '@/types/profile';

function apply(locale: LocaleState | undefined): void {
    if (!locale) {
        return;
    }

    const root = document.documentElement;
    const lang = locale.current.replace('_', '-');

    if (root.lang !== lang) {
        root.lang = lang;
    }

    if (root.dir !== locale.direction) {
        root.dir = locale.direction;
    }
}

/**
 * Keep `<html lang>` and `<html dir>` in step with the `locale` shared prop.
 *
 * `resources/views/app.blade.php` renders both for the first paint, but a
 * language switch is an ordinary Inertia visit: `locale.update` answers with a
 * redirect, the client swaps the page object, and the root template is never
 * re-rendered. Without this, picking Arabic left the document in `dir="ltr"`
 * until a hard reload.
 */
export function initializeLocaleDirection(): void {
    router.on('navigate', (event) => {
        apply((event.detail.page.props as { locale?: LocaleState }).locale);
    });
}
