<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * The recipe a copy was saved from (a shared recipe link), so opening a link to it again says the user has it
     * already instead of saving it twice. Empty again once the original is deleted.
     */
    public function up(): void
    {
        Schema::table('recipes', function (Blueprint $table) {
            $table->foreignId('copied_from_recipe_id')->nullable()->after('created_by')
                ->constrained('recipes')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('recipes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('copied_from_recipe_id');
        });
    }
};
