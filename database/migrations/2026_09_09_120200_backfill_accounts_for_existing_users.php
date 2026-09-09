<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Gives every pre-existing user an account, so nothing is left with a
 * null tenant once the scoping goes live. A user with no account_id
 * would see nothing at all under the global scope - a silent empty
 * dashboard rather than an error, which is the worst kind of bug.
 *
 * The first user becomes staff. That is a pragmatic choice, not a rule:
 * this app's first user is the person who installed it.
 */
return new class extends Migration
{
    public function up(): void
    {
        $first = true;

        foreach (DB::table('users')->whereNull('account_id')->orderBy('id')->get() as $user) {
            $accountId = DB::table('accounts')->insertGetId([
                'name' => $user->name ?: $user->email,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('users')->where('id', $user->id)->update([
                'account_id' => $accountId,
                'role' => $first ? 'staff' : 'client',
            ]);

            $first = false;
        }
    }

    public function down(): void
    {
        // Deliberately does nothing. Rolling back would orphan users
        // from accounts that later rows depend on, and the accounts
        // themselves are dropped by the create_accounts migration.
    }
};
