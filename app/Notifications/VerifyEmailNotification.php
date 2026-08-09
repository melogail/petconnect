<?php

namespace App\Notifications;

use App\Support\LocaleManager;
use Illuminate\Auth\Notifications\VerifyEmail as BaseVerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;

class VerifyEmailNotification extends BaseVerifyEmail
{
    /**
     * Build the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     */
    public function toMail($notifiable): MailMessage
    {
        $verificationUrl = $this->verificationUrl($notifiable);
        $appName = (string) config('app.name', 'PetConnect');
        $locale = method_exists($notifiable, 'preferredLocale')
            ? (string) $notifiable->preferredLocale()
            : (string) config('app.locale', 'en');
        $expireMinutes = (int) config('auth.verification.expire', 60);
        $userName = (string) ($notifiable->name ?? '');

        return (new MailMessage)
            ->subject(__('mail.verify_email.subject', ['app' => $appName], $locale))
            ->view('emails.verify-email', [
                'url' => $verificationUrl,
                'userName' => $userName,
                'userEmail' => $notifiable->getEmailForVerification(),
                'appName' => $appName,
                'locale' => $locale,
                'dir' => LocaleManager::direction($locale),
                'expireMinutes' => $expireMinutes,
                'subject' => __('mail.verify_email.subject', ['app' => $appName], $locale),
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
}
