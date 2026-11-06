<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sharing by email is gone: lists and cookbooks are only shared by invite link (InviteLinkController). The members of
 * a list or cookbook are the accepted rows of grocery_list_invites / cookbook_invites, so those tables stay; what goes
 * is what only the email invites used: the invites that were never accepted (pending or declined, also those to an
 * address without an account) and the email column they were sent to.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Not members: pending or declined email invites, and any row without a user (never anyone's membership)
        DB::table('grocery_list_invites')
            ->where(fn ($query) => $query->where('status', '!=', 'accepted')->orWhereNull('user_id'))
            ->delete();
        DB::table('cookbook_invites')
            ->where(fn ($query) => $query->where('status', '!=', 'accepted')->orWhereNull('user_id'))
            ->delete();

        Schema::table('grocery_list_invites', function (Blueprint $table) {
            $table->dropIndex(['email']);
        });
        Schema::table('grocery_list_invites', function (Blueprint $table) {
            $table->dropColumn('email');
        });

        Schema::table('cookbook_invites', function (Blueprint $table) {
            $table->dropIndex(['email', 'status']);
        });
        Schema::table('cookbook_invites', function (Blueprint $table) {
            $table->dropColumn('email');
        });
    }

    // The structure comes back (the address of a member is their account's); the deleted invites don't
    public function down(): void
    {
        Schema::table('grocery_list_invites', function (Blueprint $table) {
            $table->string('email')->nullable()->after('grocery_list_id')->index();
        });
        Schema::table('cookbook_invites', function (Blueprint $table) {
            $table->string('email')->nullable()->after('user_id');
            $table->index(['email', 'status']);
        });

        foreach (['grocery_list_invites', 'cookbook_invites'] as $tableName) {
            DB::table($tableName)
                ->whereNotNull('user_id')
                ->update(['email' => DB::raw("(select users.email from users where users.id = {$tableName}.user_id)")]);
        }
    }
};
