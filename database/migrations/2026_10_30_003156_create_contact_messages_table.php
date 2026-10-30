<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The guest channel. Support tickets are for members only — a ticket has
     * a thread and a page to follow it on, neither of which a guest has — so
     * a visitor without an account writes a one-off message here instead,
     * read in Nova and answered from the team's own mail client.
     *
     * Any ticket the previous migration carried over from a guest contact
     * (null `user_id`) is a contact message by this definition, so it is
     * moved across with its opening message and removed from `tickets`.
     */
    public function up(): void
    {
        Schema::create('contact_messages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('subject');
            $table->text('message');
            $table->timestamps();
        });

        DB::table('tickets')->whereNull('user_id')->orderBy('id')->each(function (object $ticket): void {
            $opening = DB::table('ticket_messages')->where('ticket_id', $ticket->id)->orderBy('id')->first();

            DB::table('contact_messages')->insert([
                'name'       => $ticket->name,
                'email'      => $ticket->email,
                'subject'    => $ticket->subject,
                'message'    => $opening?->body ?? '',
                'created_at' => $ticket->created_at,
                'updated_at' => $ticket->updated_at,
            ]);

            DB::table('tickets')->where('id', $ticket->id)->delete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contact_messages');
    }
};
