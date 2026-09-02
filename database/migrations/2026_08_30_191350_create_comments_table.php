<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The `commentable` columns are written out by hand rather than through
     * `$table->morphs()`, which would also declare the two-column
     * `(commentable_type, commentable_id)` index. That index is a strict prefix
     * of `comments_commentable_parent_created_at_index` below, so it narrows
     * nothing and only costs a write — the same reasoning that removed
     * `pets_status_index`, see .ai/rules/migrations.md.
     *
     * Every read of a thread is `where commentable_type = ? and commentable_id
     * = ? and parent_id is null order by created_at desc limit ?`
     * (ListCommentThread, LoadPetDetail's slice, EagerLoadFeedRelations'
     * preview), so the index spans the two equality columns, the `parent_id is
     * null` predicate and the ordering column, in that order. It is the same
     * shape as `pets_status_deleted_at_created_at_index`, where `deleted_at is
     * null` sits in the middle for the same reason.
     *
     * The prefixes still serve the counters: `withCount('comments')` filters
     * the morph pair alone and `withCount('rootComments')` the pair plus
     * `parent_id`. A future query that pins the pair and orders by `created_at`
     * *without* pinning `parent_id` would not get its ordering from this index
     * — there is no such query today, and adding one is the trigger to revisit
     * the shape.
     *
     * `comments_parent_id_created_at_index` is widened past the bare FK column
     * for Comment::replies(), which is `where parent_id = ? order by created_at
     * desc` in ListCommentReplies and `parent_id in (...)` inside the reply
     * preview's window function. `parent_id` stays the leading column so InnoDB
     * keeps adopting it as the foreign key's index instead of generating one.
     */
    public function up(): void
    {
        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('content');
            $table->foreignId('parent_id')->nullable()->constrained('comments')->cascadeOnDelete();
            $table->string('commentable_type');
            $table->unsignedBigInteger('commentable_id');
            $table->timestamps();

            $table->index('user_id');
            $table->index(['parent_id', 'created_at'], 'comments_parent_id_created_at_index');
            $table->index(['commentable_type', 'commentable_id', 'parent_id', 'created_at'], 'comments_commentable_parent_created_at_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('comments');
    }
};
