<?php

namespace App\Http\Controllers;

use App\Models\Site;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SiteController extends Controller
{
    public function index()
    {
        // Account scoping still happens via the global scope on Site
        // (see App\Support\BelongsToAccount) - visibleTo() adds the
        // finer restriction on top for a team member who only has
        // access to some of the account's sites, not all of it.
        // findings eager-loaded because issueCounts() reads them - without
        // this the listing fires a query per site, which is fine at three
        // sites and awful at fifty.
        $sites = Site::visibleTo(Auth::user())->with('latestAudit.findings')->orderBy('name')->get();

        return view('sites.index', compact('sites'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'url' => ['required', 'url:http,https', 'max:2048'],
        ]);

        // account_id is stamped by the trait's creating hook rather than
        // taken from the request - a tenant id that arrives in a form
        // post is a tenant id an attacker can change.
        $site = Site::create($data);

        // A restricted member creating a site would otherwise lose
        // sight of it immediately - Site::visibleTo() only shows a
        // member sites they've been explicitly granted, and nothing
        // grants that automatically just because they made it. Admins
        // and staff see everything regardless, so this is genuinely
        // only needed for 'member'.
        if (Auth::user()->role === 'member') {
            $site->users()->attach(Auth::id());
        }

        return redirect('/sites')->with('status', 'Site added.');
    }

    public function show(Site $site)
    {
        // Route model binding respects the account-level global scope,
        // so a user from another account gets a 404 rather than
        // someone else's data. This adds the finer restriction on top:
        // a restricted team member without an explicit grant for this
        // site also gets a 404, even though it's in their own account.
        abort_unless(Site::visibleTo(Auth::user())->where('id', $site->id)->exists(), 404);

        $audits = $site->audits()->with('findings')->paginate(20);
        $content = $site->content()->limit(10)->get();
        $competitors = $site->competitorComparisons()->limit(10)->get();
        $socialPosts = $site->socialPosts()->limit(10)->get();
        $newsletters = $site->newsletters()->limit(10)->get();
        $subscribers = $site->subscribers()->limit(50)->get();

        return view('sites.show', compact('site', 'audits', 'content', 'competitors', 'socialPosts', 'newsletters', 'subscribers'));
    }

    public function destroy(Site $site)
    {
        $site->delete();

        return redirect('/sites')->with('status', 'Site removed.');
    }

    public function updateFrequency(Request $request, Site $site)
    {
        $data = $request->validate([
            'audit_frequency' => ['required', 'in:off,weekly,monthly'],
        ]);

        $site->update($data);
        $site->rescheduleNextAudit();

        return redirect('/sites/' . $site->id)->with('status', 'Audit schedule updated.');
    }

    public function updateHost(Request $request, Site $site)
    {
        $data = $request->validate([
            'host' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
        ]);

        $site->update($data);

        return redirect('/sites/' . $site->id)->with('status', 'Site details updated.');
    }
}
