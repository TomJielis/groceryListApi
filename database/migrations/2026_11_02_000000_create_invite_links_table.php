<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Links that invite whoever opens them to a list or cookbook (shared over WhatsApp and the like, instead of an
     * email). One link can be used by several people until it expires or its maker withdraws it.
     */
    public function up(): void
    {
        Schema::create('invite_links', function (Blueprint $table) {
            $table->id();
            $table->string('token', 64)->unique();
            // One of the two: what the link invites to (gone with it)
            $table->foreignId('grocery_list_id')->nullable()->constrained('grocery_lists')->cascadeOnDelete();
            $table->foreignId('cookbook_id')->nullable()->constrained('cookbooks')->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invite_links');
    }
};
