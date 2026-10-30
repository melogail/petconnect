<?php

namespace App\Models;

use Database\Factories\ContactMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A one-off message from a visitor without an account, sent through the
 * support page's contact form and read in the back office.
 *
 * This is the guest channel; a member opens a Ticket instead, which has a
 * thread and a page to follow it on. A contact message has neither — the
 * team answers it from their own mail client to the address on the row, and
 * deletes it when it is dealt with (App\Nova\ContactMessage).
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $subject
 * @property string $message
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'subject', 'message'])]
class ContactMessage extends Model
{
    /** @use HasFactory<ContactMessageFactory> */
    use HasFactory;
}
