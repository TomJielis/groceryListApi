<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            // Empty for a question asked without an account (e.g. someone who can't log in)
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('email');
            $table->string('subject');
            // 'open' (waiting for the admin), 'answered' (waiting for the user) or 'closed'
            $table->string('status', 20)->default('open');
            // An answer the person who asked hasn't opened yet (badge in the app); cleared when they open the conversation
            $table->boolean('unread_by_user')->default(false);
            $table->string('language', 5)->default('nl');
            // Secret part of the link in the emails: whoever has it can read and answer the ticket without logging in
            $table->string('access_token', 64)->unique();
            $table->dateTime('last_message_at');
            $table->dateTime('closed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'last_message_at']);
            $table->index(['user_id', 'unread_by_user']);
            $table->index('email');
        });

        Schema::create('support_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('support_ticket_id')->constrained('support_tickets')->cascadeOnDelete();
            $table->boolean('from_admin')->default(false);
            // The admin who answered (empty for the person who asked)
            $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            // 'app' or 'website'
            $table->string('via', 20);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_messages');
        Schema::dropIfExists('support_tickets');
    }
};
