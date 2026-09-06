<?php

namespace App\Nova\Policies;

use App\Models\Admin;
use App\Nova\Category as CategoryResource;

/**
 * Authorization for the Category resource, on the `admin` guard.
 *
 * The taxonomy is the back office's own data, so creating and editing are
 * open. Deleting is not, and the reason is a database constraint rather than a
 * permission: `pets.category_id` is `restrictOnDelete` while `pets` soft
 * deletes, so a soft-deleted listing still holds its `category_id` and still
 * satisfies the foreign key. Nova's built-in delete would hand that straight
 * to the driver and produce a 500 with "FOREIGN KEY constraint failed" and no
 * explanation, on a category whose listings all look deleted.
 *
 * `delete` therefore returns false — which removes the built-in delete from
 * the detail page, the row menu and the index bulk actions — and
 * `runDestructiveAction` returns true so App\Nova\Actions\DeleteCategory can
 * run. That action counts `withTrashed()` first and refuses with a sentence.
 * See .ai/rules/migrations.md.
 */
class CategoryPolicy
{
    /**
     * Determine whether the admin can list categories.
     */
    public function viewAny(Admin $admin): bool
    {
        return true;
    }

    /**
     * Determine whether the admin can view a category.
     */
    public function view(Admin $admin, CategoryResource $resource): bool
    {
        return true;
    }

    /**
     * Determine whether the admin can create categories.
     */
    public function create(Admin $admin): bool
    {
        return true;
    }

    /**
     * Determine whether the admin can update a category.
     */
    public function update(Admin $admin, CategoryResource $resource): bool
    {
        return true;
    }

    /**
     * Determine whether the admin can delete a category with Nova's built-in
     * delete.
     *
     * No — see the class docblock. Only App\Nova\Actions\DeleteCategory may,
     * and only behind its own check.
     */
    public function delete(Admin $admin, CategoryResource $resource): bool
    {
        return false;
    }

    /**
     * Determine whether the admin may run a destructive action.
     *
     * True, which is what lets DeleteCategory past the `delete` refusal.
     */
    public function runDestructiveAction(Admin $admin, CategoryResource $resource): bool
    {
        return true;
    }

    /**
     * Determine whether the admin can restore a category.
     *
     * `categories` does not soft delete.
     */
    public function restore(Admin $admin, CategoryResource $resource): bool
    {
        return false;
    }

    /**
     * Determine whether the admin can permanently delete a category.
     */
    public function forceDelete(Admin $admin, CategoryResource $resource): bool
    {
        return false;
    }
}
