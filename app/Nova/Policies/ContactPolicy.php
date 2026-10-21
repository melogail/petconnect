<?php

namespace App\Nova\Policies;

use App\Models\Admin;
use App\Nova\Contact as ContactResource;

/**
 * Authorization for the Contact resource, on the `admin` guard.
 *
 * A contact message is what a visitor wrote to the team. An admin reads it and
 * answers it from their mail client; they do not author one and they do not
 * edit one, so `create` and `update` are false and App\Nova\Contact exposes no
 * writable field.
 *
 * `delete` is true, and deliberately so where ReportPolicy's is false: a report
 * is evidence a moderation decision hangs off, a contact message is an inbox
 * item that is finished with once it has been answered. Nothing references the
 * row, so Nova's ordinary delete — including the bulk bar — is the right tool
 * for clearing handled messages, and no DestructiveAction needs to stand in
 * for it.
 */
class ContactPolicy
{
    /**
     * Determine whether the admin can list contact messages.
     */
    public function viewAny(Admin $admin): bool
    {
        return true;
    }

    /**
     * Determine whether the admin can read a contact message.
     */
    public function view(Admin $admin, ContactResource $resource): bool
    {
        return true;
    }

    /**
     * Determine whether the admin can author a contact message.
     *
     * No: messages come from visitors, through the support page.
     */
    public function create(Admin $admin): bool
    {
        return false;
    }

    /**
     * Determine whether the admin can edit a contact message.
     *
     * No: the sender's own words are the message.
     */
    public function update(Admin $admin, ContactResource $resource): bool
    {
        return false;
    }

    /**
     * Determine whether the admin can delete a handled contact message.
     */
    public function delete(Admin $admin, ContactResource $resource): bool
    {
        return true;
    }
}
