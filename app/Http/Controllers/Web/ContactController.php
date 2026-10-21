<?php

namespace App\Http\Controllers\Web;

use App\Actions\Contact\SubmitContactMessage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Contact\StoreContactRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Messages sent through the support page's contact form.
 *
 * One action, `store`, named rather than `__invoke` so Wayfinder gives the
 * frontend a verb (`ContactController.store`) the same as every other write.
 *
 * Public on purpose: "how do I reach a human" is the question a visitor
 * without an account is most likely to have, so the route sits outside the
 * `auth` group and the sender may be a guest. Who the sender is decides the
 * form — StoreContactRequest requires `name` and `email` from a guest and
 * ignores them from a member, whose account supplies both.
 *
 * No `$this->authorize()`: nothing here loads or exposes a model somebody else
 * owns, which is the one exemption .ai/rules/controllers.md allows.
 */
class ContactController extends Controller
{
    public function store(StoreContactRequest $request, SubmitContactMessage $submitContactMessage): RedirectResponse
    {
        $submitContactMessage->handle(
            sender: $request->user(),
            name: $request->senderName(),
            email: $request->senderEmail(),
            ticketType: $request->ticketType(),
            subject: $request->subject(),
            message: $request->message(),
        );

        Inertia::flash('toast', [
            'type'    => 'success',
            'message' => __('Your message has been sent. We will get back to you shortly.'),
        ]);

        return back();
    }
}
