<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->isNovaRequest($request)) {
            App::setLocale('en');

            return $next($request);
        }

        App::setLocale($this->resolveLocale($request));

        return $next($request);
    }

    protected function isNovaRequest(Request $request): bool
    {
        return $request->is('nova', 'nova/*', 'nova-api', 'nova-api/*', 'nova-vendor', 'nova-vendor/*');
    }

    protected function resolveLocale(Request $request): string
    {
        $available = config('app.available_locales', ['en', 'ar']);

        $candidates = [
            $request->cookie('locale'),
            $request->user()?->locale,
            $request->session()->get('locale'),
            config('app.locale', 'en'),
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && in_array($candidate, $available, true)) {
                return $candidate;
            }
        }

        return 'en';
    }
}
