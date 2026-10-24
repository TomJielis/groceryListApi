<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('push_tokens', function (Blueprint $table) {
            // The login the device registered with. Ending that login (logout, block, password reset) deletes the
            // push token too, so the device stops getting pushes for this account. Null for rows from before this.
            $table->foreignId('access_token_id')->nullable()->after('user_id')
                ->constrained('personal_access_tokens')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('push_tokens', function (Blueprint $table) {
            $table->dropConstrainedForeignId('access_token_id');
        });
    }
};
