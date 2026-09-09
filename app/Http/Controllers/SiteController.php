<?php

namespace App\Http\Controllers;

use App\Models\Site;
use Illuminate\Http\Request;

class SiteController extends Controller
{
    public function index()
    {
        // No explicit account filter - the global scope on Site does it.
        // See App\Support\BelongsToAccount.
        $sites = Site::with('latestAudit')->orderBy('name')->get();

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
        Site::create($data);

        return redirect('/sites')->with('status', 'Site added.');
    }

    public function show(Site $site)
    {
        // Route model binding respects the global scope, so a client
        // requesting another account's site id gets a 404 rather than
        // someone else's data.
        $audits = $site->audits()->paginate(20);

        return view('sites.show', compact('site', 'audits'));
    }

    public function destroy(Site $site)
    {
        $site->delete();

        return redirect('/sites')->with('status', 'Site removed.');
    }
}
