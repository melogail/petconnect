import { createInertiaApp } from '@inertiajs/vue3';
import { initializeTheme } from '@/composables/useAppearance';
import AppLayout from '@/layouts/AppLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { initializeFlashToast } from '@/lib/flashToast';
import { initializeLocaleDirection } from '@/lib/localeDirection';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            // The bare splash page owns its whole viewport.
            case name === 'Welcome':
                return null;
            // Public pages: the app sidebar assumes a signed-in user, and all
            // five of these are reachable by guests, so they get the public
            // shell instead.
            case name === 'Home':
            case name === 'pets/Show':
            case name === 'profile/Show':
            case name === 'Help':
            case name === 'Support':
                return PublicLayout;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on page load...
initializeTheme();

// This will listen for flash toast data from the server...
initializeFlashToast();

// This keeps <html lang> / <html dir> in step with the locale shared prop...
initializeLocaleDirection();
