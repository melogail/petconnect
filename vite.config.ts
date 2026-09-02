import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import { defineConfig, lazyPlugins } from 'vite-plus';

export default defineConfig({
    plugins: lazyPlugins(() => [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.ts'],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        inertia(),
        tailwindcss(),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        wayfinder({
            formVariants: true,
        }),
    ]),
    server: {
        watch: {
            // Vite's resolveChokidarOptions PREPENDS its own defaults
            // (.git, node_modules, test-results and the cache dir) to
            // whatever is listed here, so this array EXTENDS the defaults
            // rather than replacing them. .git and node_modules are
            // therefore redundant, but kept explicit because they are the
            // two everyone assumes an explicit list silently drops.
            //
            // inotify allocates one watch per DIRECTORY, not per file, so
            // directory counts decide what is worth ignoring; reasoning
            // from file counts gets both the cause and the magnitude wrong.
            // storage is the entry that actually matters: 18,102 dirs, of
            // which storage/app/public/media alone is 17,878. Leaving it in
            // costs ~18.1k of the 65,536 per-user watches, which is what
            // makes `npm run dev` die with ENOSPC next to an editor and a
            // second project's dev server. vendor earns its place too at
            // 2,529 dirs and is not one of Vite's defaults. public was
            // deliberately removed: it is 6 dirs, and excluding it disabled
            // reload-on-change for everything under public/ in exchange.
            ignored: [
                '**/.agents/**',
                '**/.claude/**',
                '**/.cursor/**',
                '**/.git/**',
                '**/.junie/**',
                '**/node_modules/**',
                '**/storage/**',
                '**/vendor/**',
            ],
        },
    },
    lint: {
        ignorePatterns: [
            'vendor/**',
            'node_modules/**',
            'public/**',
            'bootstrap/ssr/**',
            'tailwind.config.js',
            'resources/js/actions/**',
            'resources/js/components/ui/*',
            'resources/js/routes/**',
            'resources/js/wayfinder/**',
        ],
        options: {
            denyWarnings: true,
            typeAware: true,
        },
    },
    fmt: {
        printWidth: 80,
        tabWidth: 4,
        singleQuote: true,
        semi: true,
        singleAttributePerLine: false,
        htmlWhitespaceSensitivity: 'css',
        ignorePatterns: [
            '.agents/**',
            '.ai/**',
            '.claude/**',
            '.github/**',
            '.mcp.json',
            'boost.json',
            'composer.json',
            'lang/vendor/**',
            'public/vendor/**',
            'resources/js/components/ui/*',
            'resources/views/mail/*',
        ],
        sortTailwindcss: {
            functions: ['clsx', 'cn', 'cva'],
            entryPoint: 'resources/css/app.css',
        },
    },
});
