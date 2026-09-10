<?php

namespace App\Http\Controllers;

use App\Jobs\RunCompetitorComparison;
use App\Models\CompetitorComparison;
use App\Models\Site;
use Illuminate\Http\Request;

class CompetitorController extends Controller
{
    public function store(Request $request, Site $site)
    {
        $data = $request->validate([
            'competitor_domain' => ['required', 'string', 'max:255'],
        ]);

        $comparison = $site->competitorComparisons()->create([
            'account_id' => $site->account_id,
            'competitor_domain' => $data['competitor_domain'],
            'status' => 'queued',
        ]);

        RunCompetitorComparison::dispatch($comparison->id);

        return redirect('/competitors/' . $comparison->id)
            ->with('status', 'Comparison queued. It will be ready within a minute.');
    }

    public function show(CompetitorComparison $comparison)
    {
        $comparison->load('site');

        return view('competitors.show', ['comparison' => $comparison]);
    }
}
