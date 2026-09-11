<?php

namespace App\Http\Controllers;

use App\Jobs\RunCompetitorComparison;
use App\Models\CompetitorComparison;
use App\Models\Site;
use App\Services\DataForSeoService;
use Illuminate\Http\Request;

class CompetitorController extends Controller
{
    public function discover(Site $site, DataForSeoService $dataForSeo)
    {
        $result = $dataForSeo->findCompetitors($site->url);

        return view('competitors.discover', [
            'site' => $site,
            'suggestions' => $result['domains'],
            'error' => $result['error'],
        ]);
    }

    public function store(Request $request, Site $site)
    {
        $data = $request->validate([
            'competitor_domain' => ['required', 'string', 'max:255'],
        ]);

        // Normalised before storing, not just before querying - the
        // stored value is what the result page displays, and it
        // should show the same clean domain that was actually asked
        // about, not whatever raw URL a client happened to paste in.
        $domain = DataForSeoService::normalizeDomain($data['competitor_domain']);

        $comparison = $site->competitorComparisons()->create([
            'account_id' => $site->account_id,
            'competitor_domain' => $domain,
            'status' => 'queued',
        ]);

        RunCompetitorComparison::dispatch($comparison->id);

        return redirect('/competitors/' . $comparison->id)
            ->with('status', 'Comparison queued. It will be ready within a minute.');
    }

    public function show(CompetitorComparison $comparison)
    {
        $comparison->load('site.latestAudit');

        // Top 4 only, worst-first - same ordering AuditController uses,
        // duplicated rather than shared because this page needs a short
        // taste of the findings, not the full ordered list the audit
        // page itself builds.
        $topFindings = collect();

        if ($comparison->site->latestAudit && $comparison->site->latestAudit->status === 'completed') {
            $topFindings = $comparison->site->latestAudit->findings()
                ->whereIn('status', ['fail', 'warn'])
                ->orderByRaw("FIELD(status,'fail','warn')")
                ->orderByRaw("FIELD(severity,'high','medium','low')")
                ->limit(4)
                ->get();
        }

        return view('competitors.show', ['comparison' => $comparison, 'topFindings' => $topFindings]);
    }
}
