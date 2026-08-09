<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cookie;

class LocaleManager
{
    public const COOKIE_NAME = 'locale';

    public const COOKIE_MINUTES = 525600; // 1 year

    /**
     * Apply a locale to the app, session, cookie, and optionally the user record.
     */
    public static function apply(string $locale, ?User $user = null): void
    {
        $available = config('app.available_locales', ['en', 'ar']);

        if (! in_array($locale, $available, true)) {
            $locale = config('app.locale', 'en');
        }

        App::setLocale($locale);
        session([self::COOKIE_NAME => $locale]);
        Cookie::queue(Cookie::make(self::COOKIE_NAME, $locale, self::COOKIE_MINUTES));

        if ($user !== null && $user->locale !== $locale) {
            $user->forceFill(['locale' => $locale])->save();
        }
    }

    /**
     * Resolve the HTML text direction for a locale.
     */
    public static function direction(?string $locale = null): string
    {
        return ($locale ?? App::getLocale()) === 'ar' ? 'rtl' : 'ltr';
    }

    /**
     * Load JSON translations for the given locale.
     *
     * @return array<string, string>
     */
    public static function translations(?string $locale = null): array
    {
        $locale ??= App::getLocale();
        $path = lang_path("{$locale}.json");

        if (! is_file($path)) {
            return [];
        }

        /** @var array<string, string>|null $decoded */
        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : [];
    }
}
