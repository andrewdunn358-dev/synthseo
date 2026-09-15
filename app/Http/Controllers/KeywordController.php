<?php

namespace App\Http\Controllers;

use App\Jobs\CheckKeywordRanking;
use App\Models\Site;
use App\Models\TrackedKeyword;
use Illuminate\Http\Request;

class KeywordController extends Controller
{
    public function store(Request $request, Site $site)
    {
        $data = $request->validate([
            'keyword' => ['required', 'string', 'max:255'],
        ]);

        // firstOrCreate on [site_id, keyword] - the unique constraint
        // would reject a plain create() as a duplicate, and silently
        // re-tracking an already-tracked keyword should just queue a
        // fresh check, not error.
        $tracked = TrackedKeyword::firstOrCreate(
            ['site_id' => $site->id, 'keyword' => $data['keyword']],
            ['account_id' => $site->account_id],
        );

        CheckKeywordRanking::dispatch($tracked->id);

        return redirect('/sites/' . $site->id)
            ->with('status', 'Tracking "' . $data['keyword'] . '" - the first check will be ready within a minute.');
    }

    public function checkNow(TrackedKeyword $tracked)
    {
        CheckKeywordRanking::dispatch($tracked->id);

        return redirect('/sites/' . $tracked->site_id)->with('status', 'Re-checking "' . $tracked->keyword . '".');
    }

    public function destroy(TrackedKeyword $tracked)
    {
        $siteId = $tracked->site_id;
        $tracked->delete();

        return redirect('/sites/' . $siteId)->with('status', 'Stopped tracking that keyword.');
    }
}
