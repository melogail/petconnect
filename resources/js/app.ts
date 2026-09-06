import { createInertiaApp } from '@inertiajs/vue3';
import { ConfigProvider } from 'reka-ui';
import { createApp, h } from 'vue';
import { initializeTheme } from '@/composables/useAppearance';
import AuthLayout from '@/layouts/AuthLayout.vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import RootLayout from '@/layouts/RootLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { initializeFlashToast } from '@/lib/flashToast';
import {
    initializeLocaleDirection,
    localeDirection,
} from '@/lib/localeDirection';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

/**
 * The chrome a page sits in, below `RootLayout`.
 *
 * An empty array is a page that owns its whole viewport and wants no shell.
 *
 * There is one shell, `PublicLayout` — the legacy navbar-and-footer
 * `MainLayout`, which legacy wrapped every page in. The starter kit's
 * sidebar shell (`AppLayout` → `AppSidebarLayout`, with `AppSidebar`,
 * `AppSidebarHeader`, `Breadcrumbs` and the `ui/sidebar` primitives) was
 * removed on the user's instruction (2026-09-06): messaging, the listing
 * form and settings rendered inside it while everything a guest could reach
 * rendered inside the public one, so a signed-in reader crossed between two
 * unrelated chromes on every visit. The `Dashboard` page and its route went
 * with it — it was the kit's placeholder grid and the only page with nothing
 * behind it. Settings keep their own inner shell, `SettingsLayout`, now styled
 * after the legacy profile edit screen.
 */
function resolvePageShell(name: string) {
    switch (true) {
        // The verification notice owns its whole viewport: it paints its own
        // background wash and carries its own brand lockup, so the `auth/*`
        // card shell below would give it a second heading and a second logo
        // above its own.
        case name === 'auth/VerifyEmail':
            return [];
        case name.startsWith('auth/'):
            return [AuthLayout];
        case name.startsWith('settings/'):
            return [PublicLayout, SettingsLayout];
        default:
            return [PublicLayout];
    }
}

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    // `RootLayout` wraps every shell so the single <Toaster /> is mounted
    // once, on public and signed-in pages alike.
    layout: (name) => [RootLayout, ...resolvePageShell(name)],
    progress: {
        color: '#4B5563',
    },
    // The adapter mounts the app itself when `setup` is omitted; it is spelled
    // out here for one reason — `<ConfigProvider :dir>` has to sit above `App`.
    // Reka primitives read their direction through `useDirection()`, which
    // injects this provider and falls back to `ltr` when none is mounted, and
    // the ones that matter (`DropdownMenuContent`, `SelectContent`, …) are
    // portalled to `<body>`, so `<html dir="rtl">` never reaches them through
    // the DOM. Injection follows the component tree rather than the DOM tree,
    // so a provider here does reach them. This is the ONE place direction is
    // handed to Reka: no component passes its own `dir`.
    setup({ el, App, props, plugin }) {
        const app = createApp({
            render: () =>
                h(ConfigProvider, { dir: localeDirection.value }, () =>
                    h(App, props),
                ),
        });

        app.use(plugin);

        // `el` is typed nullable because the adapter passes `null` on the
        // server; there is no `resources/js/ssr.ts`, so this entry is only ever
        // the client one. Returning the app keeps it correct either way.
        if (el) {
            app.mount(el);
        }

        return app;
    },
});

// This will set light / dark mode on page load...
initializeTheme();

// This will listen for flash toast data from the server...
initializeFlashToast();

// This keeps <html lang> / <html dir> in step with the locale shared prop...
initializeLocaleDirection();
