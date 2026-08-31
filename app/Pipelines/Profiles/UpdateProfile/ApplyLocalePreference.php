<?php

namespace App\Pipelines\Profiles\UpdateProfile;

use App\Actions\Profiles\ApplyUserLocale;
use Closure;

/**
 * Make the language the user just chose the active one.
 *
 * Conditional on **presence, never on difference**: it runs whenever the form
 * carried a non-empty `locale` at all, so an ordinary save — one that sends no
 * language control — queues no cookie and touches no session.
 *
 * The distinction is load bearing, not pedantry. This step runs after
 * PersistProfileAttributes, which has already written the column, so a
 * `$locale !== $user->locale` guard here would compare the new value against
 * itself, always conclude "unchanged", and skip the cookie and the session for
 * every language switch there is. UpdateProfileContext::locale() carries the
 * same warning; a save that repeats the stored locale costs one redundant
 * cookie, which is the whole price of getting this right.
 *
 * Runs last, after PersistProfileAttributes. The column itself is written by
 * that step as part of the ordinary attribute bag — `locale` is in User's
 * #[Fillable] — and this step is about the three things a column write does not
 * do: switch the locale for the rest of *this* request (so the redirect renders
 * in the new language rather than the old one), put it in the session, and
 * queue the year-long cookie that carries it to the next visit and to a guest
 * session after logout.
 *
 * The work is Actions\Profiles\ApplyUserLocale's rather than inline, because
 * Http\Controllers\Web\LocaleController is a second caller — the bar
 * .ai/rules/pipelines.md sets for extracting an Action out of a step. Passing
 * the user in means the Action's own `forceFill` is a no-op here, since
 * PersistProfileAttributes has already written the same value.
 */
class ApplyLocalePreference
{
    public function __construct(private readonly ApplyUserLocale $applyUserLocale) {}

    public function handle(UpdateProfileContext $context, Closure $next): mixed
    {
        $locale = $context->locale();

        if ($locale === null) {
            return $next($context);
        }

        $this->applyUserLocale->handle($locale, $context->user);

        return $next($context);
    }
}
