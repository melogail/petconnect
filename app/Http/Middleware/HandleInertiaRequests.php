<?php

namespace App\Http\Middleware;

use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\MessagingInboxService;
use App\Services\NotificationInboxService;
use App\Support\LocaleManager;
use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');

        $locale = App::getLocale();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'locale' => $locale,
            'dir' => LocaleManager::direction($locale),
            'translations' => fn () => LocaleManager::translations($locale),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'flash' => [
                'error' => fn () => request()->session()->get('error'),
                'success' => fn () => request()->session()->get('success'),
            ],
            'auth' => [
                'user' => fn () => ($user = $this->domainUser($request))
                    ? UserResource::make($user)
                    : null,
            ],
            'messaging' => fn () => ($user = $this->domainUser($request))
                ? app(MessagingInboxService::class)->sharedPropsFor($user)
                : null,
            'notifications' => fn () => ($user = $this->domainUser($request))
                ? app(NotificationInboxService::class)->sharedPropsFor($user)
                : null,
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * Resolve the authenticated front-end user, ignoring Nova admin sessions.
     */
    protected function domainUser(Request $request): ?User
    {
        $user = $request->user();

        return $user instanceof User ? $user : null;
    }
}
