{{--
    `lang` and `dir` both come from the `locale` shared prop, which
    Http\Middleware\HandleInertiaRequests derives from App::getLocale() and the
    `petconnect.locales.rtl` whitelist. Reading the prop rather than recomputing
    it here keeps one reader of that whitelist: the SetLocale middleware has
    already resolved cookie -> user -> session -> app.locale by the time this
    renders.

    Without `dir`, an Arabic page laid out left-to-right. Nova emits its own
    `dir` from its own root view and is unaffected.

    The fallback covers a render with no Inertia page object (there is none
    today; it costs a null-coalesce rather than a fatal if that changes).
--}}
@php
    $inertiaLocale = data_get($page ?? [], 'props.locale', [
        'current' => app()->getLocale(),
        'direction' => 'ltr',
    ]);
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $inertiaLocale['current']) }}" dir="{{ $inertiaLocale['direction'] }}" @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{-- Inline script to detect system dark mode preference and apply it immediately --}}
        <script>
            (function() {
                const appearance = '{{ $appearance ?? "system" }}';

                if (appearance === 'system') {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                    if (prefersDark) {
                        document.documentElement.classList.add('dark');
                    }
                }
            })();
        </script>

        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        <style>
            html {
                background-color: oklch(1 0 0);
            }

            html.dark {
                background-color: oklch(0.145 0 0);
            }
        </style>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        @fonts

        @vite(['resources/css/app.css', 'resources/js/app.ts', "resources/js/pages/{$page['component']}.vue"])
        <x-inertia::head>
            <title>{{ config('app.name', 'Laravel') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
