<?php

namespace App\Http\Controllers\Web;

use App\Actions\Profiles\ApplyUserLocale;
use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\UpdateLocaleRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Switching the interface language.
 *
 * Public: the picker is in the header of every page, including the ones a guest
 * can read, and a guest's choice has to stick. There is no model here whose
 * access is in question, so no `$this->authorize()` — the exemption
 * .ai/rules/controllers.md grants to actions with no policy-governed model.
 *
 * A POST, not a GET. It writes a cookie, a session key and — for a signed-in
 * user — a database column, and under Inertia v3 a GET that writes is a bug
 * rather than a purity argument: prefetching issues real GET requests on hover,
 * so a `GET /locale/ar` link would switch the whole site to Arabic as the
 * pointer crossed the menu.
 *
 * `back()`, so the language changes on the page the user is standing on. The
 * response is rendered *after* Actions\Profiles\ApplyUserLocale has called
 * App::setLocale(), so the redirect's own flash message is already in the new
 * language; every later request picks the locale up from the cookie through the
 * SetLocale middleware.
 */
class LocaleController extends Controller
{
    public function update(UpdateLocaleRequest $request, ApplyUserLocale $applyUserLocale): RedirectResponse
    {
        $applyUserLocale->handle($request->locale(), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Language updated.')]);

        return back();
    }
}
