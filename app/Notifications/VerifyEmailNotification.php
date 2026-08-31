<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * The branded, locale-aware verification email.
 *
 * .ai/rules/notifications.md recorded this as deferred: the legacy override
 * depended on `resources/views/emails/`, on `lang/en` + `lang/ar` and on a
 * locale mechanism, none of which had been ported. All three landed with this
 * phase, so the override is restored — `User::sendEmailVerificationNotification()`
 * sends this instead of the base class.
 *
 * **This changes what `tests/Feature/Auth/VerificationNotificationTest` sees.**
 * It asserts `Notification::assertSentTo($user, VerifyEmail::class)`, and
 * NotificationFake keys sent notifications by their exact class name rather
 * than by `instanceof`, so a subclass does not satisfy it. The assertion has to
 * become `VerifyEmailNotification::class`. That is the update
 * .ai/rules/notifications.md said would be needed in the same change; it is
 * flagged rather than made, because this agent does not edit tests.
 *
 * ## Why it extends the framework notification instead of replacing it
 *
 * `verificationUrl()` builds the signed, expiring route from
 * `auth.verification.expire` and honours `VerifyEmail::createUrlUsing()`. Every
 * one of those is behaviour the framework may change with the signature scheme,
 * and reimplementing it here would be a security-relevant fork of framework
 * code for the sake of an HTML template. Only `toMail()` is overridden.
 *
 * ## Locale is read from the notifiable, not from the request
 *
 * The mail may be rendered long after — and elsewhere than — the request that
 * triggered it, so `App::getLocale()` is the wrong source. `User` implements
 * Illuminate\Contracts\Translation\HasLocalePreference, and every string below
 * is translated with an explicit `$locale` third argument rather than by
 * switching the application locale and switching it back. The direction is
 * derived from `petconnect.locales.rtl`, so an RTL language added to the config
 * needs no change here.
 *
 * `preferredLocale()` is called through the interface, so any notifiable that
 * implements it works; anything else falls back to the application locale
 * rather than fataling.
 */
class VerifyEmailNotification extends VerifyEmail
{
    public function toMail(mixed $notifiable): MailMessage
    {
        $appName = (string) config('app.name', 'PetConnect');
        $locale = $this->localeFor($notifiable);
        $expireMinutes = (int) config('auth.verification.expire', 60);
        $userName = (string) ($notifiable->name ?? '');
        $subject = __('mail.verify_email.subject', ['app' => $appName], $locale);

        return (new MailMessage)
            ->subject($subject)
            ->view('emails.verify-email', [
                'url' => $this->verificationUrl($notifiable),
                'userEmail' => $notifiable->getEmailForVerification(),
                'appName' => $appName,
                'locale' => $locale,
                'dir' => $this->directionFor($locale),
                'subject' => $subject,
                'preheader' => __('mail.verify_email.preheader', ['app' => $appName], $locale),
                'heading' => __('mail.verify_email.heading', [], $locale),
                'intro' => __('mail.verify_email.intro', [
                    'name' => $userName !== '' ? $userName : __('mail.verify_email.friend', [], $locale),
                    'app' => $appName,
                ], $locale),
                'accountEmailLabel' => __('mail.verify_email.account_email', [], $locale),
                'body' => __('mail.verify_email.body', [], $locale),
                'action' => __('mail.verify_email.action', [], $locale),
                'tipsTitle' => __('mail.verify_email.tips_title', [], $locale),
                'tipExpiry' => __('mail.verify_email.tip_expiry', ['minutes' => $expireMinutes], $locale),
                'tipIgnore' => __('mail.verify_email.tip_ignore', [], $locale),
                'linkFallback' => __('mail.verify_email.link_fallback', [], $locale),
                'footer' => __('mail.verify_email.footer', ['app' => $appName], $locale),
            ]);
    }

    /**
     * The recipient's own language, or the application default.
     */
    protected function localeFor(mixed $notifiable): string
    {
        if ($notifiable instanceof HasLocalePreference) {
            return (string) $notifiable->preferredLocale();
        }

        return (string) config('app.locale', 'en');
    }

    /**
     * The HTML text direction for a locale, from the configured RTL list.
     */
    protected function directionFor(string $locale): string
    {
        /** @var list<string> $rtl */
        $rtl = config('petconnect.locales.rtl', []);

        return in_array($locale, $rtl, true) ? 'rtl' : 'ltr';
    }
}
