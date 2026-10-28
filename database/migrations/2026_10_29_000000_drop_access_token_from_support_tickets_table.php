<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Support is for signed-in users only now: no secret links to conversations any more
    public function up(): void
    {
        if (Schema::hasColumn('support_tickets', 'access_token')) {
            Schema::table('support_tickets', function (Blueprint $table) {
                $table->dropUnique(['access_token']);
                $table->dropColumn('access_token');
            });
        }
    }

    public function down(): void
    {
        Schema::table('support_tickets', function (Blueprint $table) {
            $table->string('access_token', 64)->nullable()->unique();
        });
    }
};
