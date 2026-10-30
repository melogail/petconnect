<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Internal documentation site
    |--------------------------------------------------------------------------
    |
    | The VitePress build under docs/ is served by Laravel on its own host,
    | behind the `admin` guard, so nothing in it is reachable by the public or
    | by crawlers. Build it with `npm run docs:build`.
    |
    */

    'domain' => env('DOCS_DOMAIN', 'docs.'.parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST)),

    'path'   => base_path('docs/.vitepress/dist'),

];
