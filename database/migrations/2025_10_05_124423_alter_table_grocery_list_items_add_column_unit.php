<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('grocery_list_items', function (Blueprint $table) {
            $table->enum('unit', ['g', 'kg', 'L', 'ml'])->nullable()->after('unit_price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('grocery_list_items', function (Blueprint $table) {
            $table->dropColumn('unit');
        });
    }
};
