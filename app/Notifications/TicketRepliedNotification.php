<?php

namespace App\Notifications;

use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the sender of a support ticket that the team wrote back, or closed
 * it (`$reply` is null for a close).
 *
 * Sent from Actions\Tickets\ReplyToTicket and Actions\Tickets\CloseTicket,
 * only when the actor is an Admin. Two kinds of recipient: the member gets a
 * database row for the bell plus an email; when their account has since been
 * deleted, an `AnonymousNotifiable` routed to the address on the row gets the
 * email alone — there is no page left to read the reply on, so the email
 * carries the reply itself.
 *
 * The mail renders at send time in the recipient's locale: the framework
 * wraps sending in `withLocale(preferredLocale())` for a `User`, and the
 * on-demand recipient gets the application default. Laravel's stock
 * notification template is used rather than the branded `emails/verify-email`
 * one.
 */
class TicketRepliedNotification extends Notification implements ShouldQueueAfterCommit
{
    use Queueable;

    public function __construct(
        public Ticket $ticket,
        public ?TicketMessage $reply,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return $notifiable instanceof User ? ['database', 'mail'] : ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $replace = ['subject' => $this->ticket->subject];

        $mail    = (new MailMessage)
            ->subject(__($this->messageKey(), $replace))
            ->greeting(__('mail.ticket.greeting', ['name' => $this->ticket->name]))
            ->line(__($this->messageKey(), $replace));

        if ($this->reply !== null) {
            $mail->line($this->reply->body);
        }

        if ($notifiable instanceof User) {
            $mail->action(__('mail.ticket.action'), route('tickets.show', $this->ticket));
        }

        return $mail;
    }

    /**
     * The payload carries translation keys and their replacements, never
     * rendered text (.ai/rules/notifications.md).
     *
     * @return array{
     *     ticket_id: int,
     *     status: string,
     *     message_key: string,
     *     message_replace: array{subject: string},
     *     url: string,
     *     type: string
     * }
     */
    public function toArray(object $notifiable): array
    {
        return [
            'ticket_id'       => $this->ticket->id,
            'status'          => $this->ticket->status->value,
            'message_key'     => $this->messageKey(),
            'message_replace' => ['subject' => $this->ticket->subject],
            'url'             => route('tickets.show', $this->ticket),
            'type'            => 'ticket',
        ];
    }

    protected function messageKey(): string
    {
        return $this->reply === null
            ? 'notifications.ticket_closed'
            : 'notifications.ticket_answered';
    }
}
