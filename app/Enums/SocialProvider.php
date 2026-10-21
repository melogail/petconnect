<?php

namespace App\Enums;

use App\Concerns\HasOptions;

/**
 * The OAuth providers a member may sign in with.
 *
 * Backing values are Socialite driver names, and `config/services.php` is keyed
 * on the same strings — Socialite resolves both the driver and its credentials
 * from them, so adding a case means adding a `services.{value}` block too.
 *
 * The routes bind `{provider}` to this enum, so an unknown provider is a 404
 * before any controller runs; `isConfigured()` turns a *known but unset*
 * provider into the same 404 rather than a Socialite exception about a missing
 * client id.
 */
enum SocialProvider: string
{
    use HasOptions;

    case Google   = 'google';
    case Facebook = 'facebook';

    public function label(): string
    {
        return match ($this) {
            self::Google   => 'Google',
            self::Facebook => 'Facebook',
        };
    }

    /**
     * Whether this provider has credentials to sign in with.
     */
    public function isConfigured(): bool
    {
        return (string) config("services.{$this->value}.client_id") !== ''
            && (string) config("services.{$this->value}.client_secret") !== '';
    }

    /**
     * The providers that can actually be used in this environment.
     *
     * @return list<self>
     */
    public static function configured(): array
    {
        return array_values(array_filter(self::cases(), fn (self $provider): bool => $provider->isConfigured()));
    }

    /**
     * `configured()` as `{value, label}` rows for the login and register pages.
     *
     * @return list<array{value: string, label: string}>
     */
    public static function configuredOptions(): array
    {
        return array_map(
            fn (self $provider): array => ['value' => $provider->value, 'label' => $provider->label()],
            self::configured(),
        );
    }
}
