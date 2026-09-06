import type { Auth } from '@/types/auth';
import type { LocaleState } from '@/types/profile';
import type { TranslationCatalogue } from '@/types/translations';

// Extend ImportMeta interface for Vite...
declare module 'vite/client' {
    interface ImportMetaEnv {
        readonly VITE_APP_NAME: string;
        [key: string]: string | boolean | undefined;
    }

    interface ImportMeta {
        readonly env: ImportMetaEnv;
        readonly glob: <T>(pattern: string) => Record<string, () => Promise<T>>;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            /** Shared by HandleInertiaRequests on every page, guests included. */
            locale: LocaleState;
            /**
             * The active locale's catalogue — and **optional on purpose**.
             *
             * `HandleInertiaRequests::shareOnce()` shares it as an
             * `Inertia::once()` prop keyed `translations.{locale}`, so it rides
             * the initial document and is absent from the props of every
             * Inertia visit afterwards while the client reuses the copy it
             * holds. A required type here would be a lie for the majority of
             * responses and would let a call site drop the `?? {}` that
             * `useTranslations()` depends on. Read it through `t()`, never
             * straight off `page.props`.
             */
            translations?: TranslationCatalogue;
            [key: string]: unknown;
        };
    }
}

declare module 'vue' {
    interface ComponentCustomProperties {
        $inertia: typeof Router;
        $page: Page;
        $headManager: ReturnType<typeof createHeadManager>;
    }
}
