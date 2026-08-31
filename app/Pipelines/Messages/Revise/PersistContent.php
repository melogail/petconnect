<?php

namespace App\Pipelines\Messages\Revise;

use Closure;

/**
 * Write the cleaned text back onto the message, and stamp it as edited.
 *
 * A single-row update, so there is no transaction here — one statement is
 * already atomic, and Send\PersistMessage is a single INSERT with none either.
 *
 * `content` and `edited_at` are the only columns touched: an edit cannot move a
 * message to another conversation, reassign its sender, change its type or pin
 * it. The legacy MessageService passed the model to a repository that called
 * `update()` and then `refresh()`, spending a second SELECT to re-read a row it
 * had just written; the model is right here and saving already reflects the
 * change on it.
 *
 * This step is the **only** writer of `edited_at`, which is what makes
 * `Message::$is_edited` mean "the sender revised the words" and nothing else.
 * The column is outside #[Fillable] (an edit is the application's record, not a
 * claim the client may post), so it is assigned rather than passed to update():
 * `Model::preventSilentlyDiscardingAttributes` would throw on a bag holding it.
 *
 * `updated_at` moves too, but it is no longer the signal — it is the row's
 * last-write stamp and pinning, a restore or a future delivery-state column
 * move it without anybody having rewritten a message. Stamping the edit
 * explicitly is also what keeps a bounded edit window meaningful: see
 * MessagePolicy::update and `petconnect.messaging.edit_window_minutes`.
 */
class PersistContent
{
    public function handle(ReviseMessageContext $context, Closure $next): mixed
    {
        $message = $context->message();

        $message->content = $context->content();
        $message->edited_at = now();

        $message->save();

        return $next($context);
    }
}
