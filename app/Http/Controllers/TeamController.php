<?php

namespace App\Http\Controllers;

use App\Models\Site;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Team member management, scoped to the current user's own account.
 *
 * User has no BelongsToAccount global scope (Auth needs to find users
 * by email regardless of "current" account context), so every method
 * here checks account_id explicitly rather than relying on a scope to
 * do it automatically - the one place in this controller worth
 * reading twice, since it is the actual security boundary.
 */
class TeamController extends Controller
{
    public function index()
    {
        abort_unless(Auth::user()->canManageTeam(), 403);

        $accountId = Auth::user()->account_id;

        $team = User::where('account_id', $accountId)->orderBy('name')->get();
        $sites = Site::where('account_id', $accountId)->orderBy('name')->get();

        // Preloaded once for all members rather than a query per row -
        // fine at three team members, not fine at thirty.
        $grants = DB::table('site_user')
            ->whereIn('user_id', $team->pluck('id'))
            ->get()
            ->groupBy('user_id')
            ->map(fn ($rows) => $rows->pluck('site_id')->all());

        return view('team.index', compact('team', 'sites', 'grants'));
    }

    public function store(Request $request)
    {
        abort_unless(Auth::user()->canManageTeam(), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'min:8'],
            'role' => ['required', 'in:admin,member'],
            'site_ids' => ['array'],
            'site_ids.*' => ['integer'],
        ]);

        $user = User::create([
            'account_id' => Auth::user()->account_id,
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => $data['role'],
        ]);

        if ($data['role'] === 'member' && ! empty($data['site_ids'])) {
            $user->sites()->sync($this->ownSiteIds($data['site_ids']));
        }

        return redirect('/team')->with('status', $user->name . ' added. Tell them their password directly - nothing is emailed automatically yet.');
    }

    public function updateAccess(Request $request, User $teamMember)
    {
        abort_unless(Auth::user()->canManageTeam(), 403);
        abort_unless($teamMember->account_id === Auth::user()->account_id, 403);

        $data = $request->validate([
            'site_ids' => ['array'],
            'site_ids.*' => ['integer'],
        ]);

        $teamMember->sites()->sync($this->ownSiteIds($data['site_ids'] ?? []));

        return redirect('/team')->with('status', 'Access updated for ' . $teamMember->name . '.');
    }

    public function destroy(User $teamMember)
    {
        abort_unless(Auth::user()->canManageTeam(), 403);
        abort_unless($teamMember->account_id === Auth::user()->account_id, 403);
        abort_if($teamMember->id === Auth::id(), 403);

        $teamMember->delete();

        return redirect('/team')->with('status', 'Team member removed.');
    }

    /** Never trusts site ids straight from a form post - only ever
     *  grants access to sites that actually belong to the current
     *  user's own account, silently dropping anything else. */
    private function ownSiteIds(array $siteIds): array
    {
        return Site::where('account_id', Auth::user()->account_id)
            ->whereIn('id', $siteIds)
            ->pluck('id')
            ->all();
    }
}
