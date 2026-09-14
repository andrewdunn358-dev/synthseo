<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Two new roles alongside the existing 'staff' (cross-account, see
 * User::isStaff): 'admin' sees every site in their own account, same
 * as 'client' always has - 'member' sees only sites explicitly
 * granted via this pivot table. The distinction only exists because
 * an account with a real team needs someone who can see everything
 * they're responsible for (the admin) and people scoped to just the
 * clients they're actually working on (members).
 *
 * Existing 'client' rows are migrated to 'admin' here, not left as
 * 'client' - every one of them was, until this migration, someone who
 * saw every site in their account unrestricted. Silently reinterpreting
 * that as the new restricted 'member' role would have locked people
 * out of sites they already had access to; 'admin' preserves the
 * access they already had.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_user', function (Blueprint $table) {
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['site_id', 'user_id']);
        });

        DB::table('users')->where('role', 'client')->update(['role' => 'admin']);
    }

    public function down(): void
    {
        Schema::dropIfExists('site_user');

        // Not reversed the other way - by the time this migration
        // would be rolled back, new 'admin' rows may include people
        // who were never 'client' to begin with (created via the
        // team feature this migration exists to support), and
        // blindly converting them back to 'client' would be wrong.
    }
};
