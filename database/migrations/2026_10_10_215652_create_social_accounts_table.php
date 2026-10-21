<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per (provider, provider user id) a SniffPal account has signed in
     * with. A user may hold one row per provider, so Google and Facebook can
     * both be linked to the same account; the unique index is what stops one
     * Google identity from being attached to two accounts.
     *
     * `cascadeOnDelete` is enough here: the row is a plain child of `users` with
     * no media, no polymorphic children and no notifications hanging off it, so
     * the account-delete flow needs no step for it.
     *
     * `user_id` is indexed explicitly rather than relying on the FK: SQLite does
     * not create one, and MySQL's implicit FK index is not something to lean on
     * (.ai/rules/migrations.md).
     */
    public function up(): void
    {
        Schema::create('social_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 32);
            $table->string('provider_id');
            $table->timestamps();

            $table->unique(['provider', 'provider_id']);
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('social_accounts');
    }
};
