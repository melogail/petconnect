<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The `notifiable` columns are written out by hand rather than through
     * `$table->morphs()` because `morphs()` also declares the two-column
     * `(notifiable_type, notifiable_id)` index, and that index is a strict
     * prefix of both composites below — it narrows nothing they do not already
     * narrow and only costs a write on every insert. Same reasoning that
     * removed `pets_status_index`, see .ai/rules/migrations.md.
     *
     * Both composites exist because opening the bell issues two queries against
     * this table, and they pin the same equality columns but end differently:
     *
     * - `$user->notifications()->latest()->paginate()` is
     *   `where notifiable_type = ? and notifiable_id = ? order by created_at
     *   desc limit ?`, served by the `created_at` composite.
     * - `$user->unreadNotifications()->count()` is
     *   `where notifiable_type = ? and notifiable_id = ? and read_at is null`,
     *   served by the `read_at` composite as a covered count — and the same
     *   shape backs `MarkAllNotificationsAsRead`'s UPDATE.
     *
     * This is the fastest-growing per-user table in the application and nothing
     * prunes it, so the ordering column has to be inside the index rather than
     * left to a sort of every row the user has ever received.
     */
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->string('notifiable_type');
            $table->unsignedBigInteger('notifiable_id');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['notifiable_type', 'notifiable_id', 'created_at'], 'notifications_notifiable_created_at_index');
            $table->index(['notifiable_type', 'notifiable_id', 'read_at'], 'notifications_notifiable_read_at_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
