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
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('accepted_terms')->default(false)->after('email_verified_at');
            $table->dateTime('accepted_terms_at')->nullable()->after('accepted_terms');
            $table->string('accepted_terms_version')->nullable()->after('accepted_terms_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('accepted_terms');
            $table->dropColumn('accepted_terms_at');
            $table->dropColumn('accepted_terms_version');
        });
    }
};
