<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Shows the "week groceries planner" button in the app; on for everyone unless switched off in the profile
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('week_planner')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('week_planner');
        });
    }
};
