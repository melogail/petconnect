import '../css/app.css';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { Fragment, createApp, h } from 'vue';
import { ZiggyVue } from 'ziggy-js';
import Sonner from '@/components/ui/sonner/Sonner.vue';
import { registerInertiaFlashListeners } from './composables/useFlashToast';
import { initializeTheme } from './composables/useAppearance';

const appName = import.meta.env.VITE_APP_NAME || 'PetConnect';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(
            `./pages/${name}.vue`,
            import.meta.glob<DefineComponent>('./pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        const app = createApp({
            render: () =>
                h(Fragment, [
                    h(Sonner),
                    h(App, props),
                ]),
        });

        app.use(plugin).use(ZiggyVue).mount(el);

        registerInertiaFlashListeners(props.initialPage.props);
    },
    progress: {
        color: '#8B5CF6',
    },
});

initializeTheme();
