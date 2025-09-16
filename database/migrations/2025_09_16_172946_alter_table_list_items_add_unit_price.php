<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('grocery_list_items', function (Blueprint $table) {
            $table->double('unit_price')->after('quantity')->nullable()->default(null);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('grocery_list_items', function (Blueprint $table) {
            $table->dropColumn('unit_price');
        });
    }
};
