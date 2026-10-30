import { defineConfig } from 'vitepress';

// Internal docs: served by Laravel on docs.<app host> behind the admin guard
// (routes/docs.php). The robots meta mirrors the X-Robots-Tag header the
// server sends, so a saved copy of a page still carries the instruction.
export default defineConfig({
    title: 'SniffPal Docs',
    description: 'Internal engineering documentation for the SniffPal pet marketplace.',
    lang: 'en',
    cleanUrls: true,
    lastUpdated: false,
    sitemap: undefined,
    head: [
        ['meta', { name: 'robots', content: 'noindex, nofollow' }],
        ['link', { rel: 'icon', href: '/favicon.svg' }],
    ],
    // The root vite.config.ts belongs to the app (Laravel, Inertia, Tailwind
    // plugins); VitePress must not pick it up.
    vite: { configFile: false },
    themeConfig: {
        nav: [
            { text: 'Guide', link: '/guide/getting-started' },
            { text: 'Architecture', link: '/architecture/overview' },
            { text: 'Codebase', link: '/codebase/listing-lifecycle' },
            { text: 'API', link: '/api/overview' },
        ],
        sidebar: [
            {
                text: 'Guide',
                items: [
                    { text: 'Getting started', link: '/guide/getting-started' },
                    { text: 'Daily workflow', link: '/guide/workflow' },
                    { text: 'Testing & quality', link: '/guide/testing' },
                    { text: 'This docs site', link: '/guide/docs-site' },
                ],
            },
            {
                text: 'Architecture',
                items: [
                    { text: 'Overview', link: '/architecture/overview' },
                    { text: 'Backend layers', link: '/architecture/backend' },
                    { text: 'Request pipeline', link: '/architecture/middleware' },
                    { text: 'Authentication & admin', link: '/architecture/auth' },
                    { text: 'Frontend', link: '/architecture/frontend' },
                    { text: 'Data model', link: '/architecture/data-model' },
                ],
            },
            {
                text: 'Features',
                items: [
                    { text: 'Listings', link: '/features/listings' },
                    { text: 'Social & moderation', link: '/features/social' },
                    { text: 'Messaging & notifications', link: '/features/messaging' },
                    { text: 'Localization', link: '/features/localization' },
                ],
            },
            {
                text: 'Codebase',
                items: [
                    { text: 'A listing, end to end', link: '/codebase/listing-lifecycle' },
                    { text: 'Backend patterns', link: '/codebase/backend-patterns' },
                    { text: 'Frontend patterns', link: '/codebase/frontend-patterns' },
                    { text: 'Admin patterns', link: '/codebase/admin-patterns' },
                    { text: 'Testing patterns', link: '/codebase/testing-patterns' },
                ],
            },
            {
                text: 'API v1',
                items: [
                    { text: 'Overview', link: '/api/overview' },
                    { text: 'Endpoints', link: '/api/endpoints' },
                ],
            },
        ],
        outline: [2, 3],
        search: { provider: 'local' },
        footer: {
            message: 'Internal. Not for distribution.',
        },
    },
});
