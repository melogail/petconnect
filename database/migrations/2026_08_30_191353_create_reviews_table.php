<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('rate');
            $table->text('comment')->nullable();
            $table->morphs('reviewable');
            $table->timestamps();

            /**
             * One review per author per target, enforced by the database.
             *
             * The legacy app had no constraint here at all, so the same user
             * could review the same profile without limit and every average
             * rating on the site was whatever the most persistent reviewer
             * wanted it to be. An application-level check cannot close that on
             * its own: two concurrent posts both read "no review yet" and both
             * write. Pipelines\Reviews\SubmitReview\EnsureNotAlreadyReviewed is
             * the friendly fast path; this index is the guarantee, and
             * PersistReview converts the violation into a field error.
             *
             * Mirrors the identical index on `reports`, and replaces the
             * standalone index('user_id') that used to sit here: `user_id` is
             * the leading column of this index, so a separate one would be
             * redundant on SQLite and adopted-then-ignored by InnoDB. See
             * .ai/rules/migrations.md.
             */
            $table->unique(['user_id', 'reviewable_type', 'reviewable_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
