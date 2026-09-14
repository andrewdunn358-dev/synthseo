<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\AdminAction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Cross-account management for staff only. Everything here bypasses
 * the account isolation every other controller in this app is built
 * around, so every mutating action is logged via AdminAction::record -
 * see that model's class doc for why. Read this file with more
 * suspicion than the rest of the app; it is the one place account
 * isolation is meant to not apply.
 */
class PlatformController extends Controller
{
    public function index()
    {
        abort_unless(Auth::user()->isStaff(), 403);

        $accounts = Account::withCount('sites')->with('users')->orderBy('name')->get();
        $recentActions = AdminAction::with('staff')->latest('created_at')->limit(20)->get();

        return view('platform.index', compact('accounts', 'recentActions'));
    }

    public function updateUser(Request $request, User $user)
    {
        abort_unless(Auth::user()->isStaff(), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,' . $user->id],
            // Deliberately excludes 'staff' - see User::isStaff(). That
            // promotion stays a manual, deliberate tinker action, not
            // something reachable through a dropdown in this screen.
            'role' => ['required', 'in:admin,member'],
        ]);

        $before = $user->only(['name', 'email', 'role']);
        $user->update($data);

        AdminAction::record(
            'updated_user',
            'User',
            $user->id,
            'Changed ' . json_encode($before) . ' to ' . json_encode($data),
        );

        return redirect('/platform')->with('status', $user->name . ' updated.');
    }

    public function destroyUser(User $user)
    {
        abort_unless(Auth::user()->isStaff(), 403);
        abort_if($user->id === Auth::id(), 403);

        AdminAction::record(
            'deleted_user',
            'User',
            $user->id,
            "{$user->name} <{$user->email}> (account #{$user->account_id})",
        );

        $user->delete();

        return redirect('/platform')->with('status', 'User removed.');
    }

    /**
     * Deletes an account and everything attached to it - sites, audits,
     * content, everything cascades via the existing foreign keys.
     *
     * Users are deleted explicitly here rather than left to the FK's
     * own nullOnDelete behaviour. That default exists so a users-table
     * migration could run against existing rows (see its own doc
     * comment) - it was never meant as an erasure policy, and leaving
     * a deleted account's users sitting in the database with
     * account_id set to null would mean their name, email, and
     * password hash still exist indefinitely. Real deletion of an
     * account's data should mean its people's data is actually gone
     * too, not just unreachable through the app.
     */
    public function destroyAccount(Account $account)
    {
        abort_unless(Auth::user()->isStaff(), 403);

        $details = "{$account->name} (#{$account->id}), "
            . $account->users()->count() . ' users, '
            . $account->sites()->count() . ' sites';

        AdminAction::record('deleted_account', 'Account', $account->id, $details);

        $account->users()->delete();
        $account->delete();

        return redirect('/platform')->with('status', 'Account and all its data removed.');
    }
}
