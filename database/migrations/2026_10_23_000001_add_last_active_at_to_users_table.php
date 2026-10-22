<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Last request from the app or website (see TrackLastActive); unlike a token's last_used_at it
            // survives logging out
            $table->dateTime('last_active_at')->nullable()->index();
        });

        // Start from the best we know: the most recent use of any of the user's tokens
        DB::table('users')->update([
            'last_active_at' => DB::raw("(SELECT MAX(last_used_at) FROM personal_access_tokens
                WHERE personal_access_tokens.tokenable_id = users.id
                AND personal_access_tokens.tokenable_type = 'App\\\\Models\\\\User')"),
        ]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['last_active_at']);
            $table->dropColumn('last_active_at');
        });
    }
};
