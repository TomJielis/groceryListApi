<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('successful_logins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('ip_address', 45);
            // 'app', 'website' or 'other', worked out from the user agent
            $table->string('client', 20);
            // The app's build number, e.g. 28 (only for the app)
            $table->string('app_version', 20)->nullable();
            $table->string('user_agent')->nullable();
            $table->dateTime('logged_in_at');
            $table->timestamps();

            $table->index(['user_id', 'logged_in_at']);
            $table->index(['ip_address', 'logged_in_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('successful_logins');
    }
};
