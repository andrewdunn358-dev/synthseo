<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ties each user to an account and gives them a role.
 *
 * Two roles, deliberately not more until there's a reason:
 *   staff  - Synthesis IT. Sees every account.
 *   client - sees only their own account's sites and audits.
 *
 * account_id is nullable so this migration can run against a table that
 * already has users in it (there is one). Backfilling happens in the
 * next migration rather than here, because a data change and a schema
 * change in the same migration is painful to roll back.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('account_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->string('role')->default('client')->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['account_id']);
            $table->dropColumn(['account_id', 'role']);
        });
    }
};
