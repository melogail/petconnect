<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The support desk: a `tickets` header row and a `ticket_messages` thread
     * under it, replacing the single-message `contacts` inbox.
     *
     * The old table is carried across rather than dropped cold — each contact
     * row becomes a ticket whose first message is what the sender wrote — then
     * removed, so a checkout that already ran `create_contacts_table` ends up
     * on the same schema as a fresh one.
     */
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('email');
            $table->string('category')->default('other');
            $table->string('subject');
            $table->string('status')->default('open');
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            // The member's list: `where user_id = ? order by last_activity_at desc`.
            $table->index(['user_id', 'last_activity_at']);
            // The back office's queue: `where status = ? order by last_activity_at`.
            $table->index(['status', 'last_activity_at']);
        });

        Schema::create('ticket_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            // Who wrote it: a `user`, an `admin`, or null for a guest's opening
            // message. Written by hand without morphs() because nothing queries
            // by author — the thread is read through ticket_id (.ai/rules/migrations.md).
            $table->string('author_type')->nullable();
            $table->unsignedBigInteger('author_id')->nullable();
            $table->text('body');
            $table->timestamps();

            $table->index(['ticket_id', 'created_at']);
        });

        if (Schema::hasTable('contacts')) {
            DB::table('contacts')->orderBy('id')->each(function (object $contact): void {
                $ticketId = DB::table('tickets')->insertGetId([
                    'user_id'          => $contact->user_id,
                    'name'             => $contact->name,
                    'email'            => $contact->email,
                    'category'         => $contact->ticket_type,
                    'subject'          => $contact->subject,
                    'last_activity_at' => $contact->created_at,
                    'created_at'       => $contact->created_at,
                    'updated_at'       => $contact->updated_at,
                ]);

                DB::table('ticket_messages')->insert([
                    'ticket_id'   => $ticketId,
                    'author_type' => $contact->user_id === null ? null : 'user',
                    'author_id'   => $contact->user_id,
                    'body'        => $contact->message,
                    'created_at'  => $contact->created_at,
                    'updated_at'  => $contact->created_at,
                ]);
            });

            Schema::drop('contacts');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ticket_messages');
        Schema::dropIfExists('tickets');
    }
};
