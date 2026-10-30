<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // The items endpoints look items up per list and group them by name; without this every request reads the whole
    // table (all users' items)
    public function up(): void
    {
        Schema::table('grocery_list_items', function (Blueprint $table) {
            $table->index(['list_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::table('grocery_list_items', function (Blueprint $table) {
            $table->dropIndex(['list_id', 'name']);
        });
    }
};
