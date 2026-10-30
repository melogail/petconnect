<?php

namespace App\Http\Controllers\Web;

use App\Actions\Contact\SubmitContactMessage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Contact\StoreContactRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * The guest channel: a one-off message from the support page's contact form.
 *
 * Public on purpose — "how do I reach a human" is the question a visitor
 * without an account is most likely to have. A member is shown the ticket
 * dialog on that page instead (Settings\TicketController::store); this route
 * does not refuse a member, it simply has no reason to be offered to one.
 *
 * One action, `store`, named rather than `__invoke` so Wayfinder gives the
 * frontend a verb (`ContactController.store`) the same as every other write.
 *
 * No `$this->authorize()`: nothing here loads or exposes a model somebody else
 * owns, which is the one exemption .ai/rules/controllers.md allows.
 */
class ContactController extends Controller
{
    public function store(StoreContactRequest $request, SubmitContactMessage $submitContactMessage): RedirectResponse
    {
        $submitContactMessage->handle(
            name: $request->senderName(),
            email: $request->senderEmail(),
            subject: $request->subject(),
            message: $request->message(),
        );

        Inertia::flash('toast', [
            'type'    => 'success',
            'message' => __('Your message has been sent. We will get back to you by email shortly.'),
        ]);

        return back();
    }
}
