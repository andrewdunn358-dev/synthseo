<?php

namespace App\Http\Controllers;

use App\Jobs\RunCompetitorComparison;
use App\Models\CompetitorComparison;
use App\Models\Site;
use App\Services\ClaudeContentService;
use App\Services\DataForSeoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class CompetitorController extends Controller
{
    /**
     * The one-click version: reads the site's own homepage to work out
     * what kind of business it is, combines that with the location set
     * on the site, and searches live Google results for that - "look
     * up competitors" with nothing to type in, as opposed to search()
     * below, which is the same underlying search but for a phrase
     * typed in by hand.
     *
     * Location is not inferred alongside the category, deliberately -
     * see Site's migration doc comment. A wrong location guessed from
     * page text (a testimonial mentioning a different town, a footer
     * copyright notice) would silently produce a locally-irrelevant
     * search with no obvious sign anything was wrong; requiring it to
     * be set by hand means the query is always built from a fact, not
     * a guess.
     */
    public function lookup(Site $site, ClaudeContentService $claude, DataForSeoService $dataForSeo)
    {
        if (! $site->location) {
            return redirect('/sites/' . $site->id)
                ->with('status', 'Set a location for this site first (see the Platform/Hosted-with row), then try "Look up competitors" again.');
        }

        try {
            $response = Http::timeout(15)
                ->withHeaders(['User-Agent' => 'SynthSEO-Audit/1.0 (+https://synthseo.co.uk)'])
                ->get($site->url);
        } catch (\Throwable) {
            return view('competitors.search', [
                'site' => $site,
                'query' => null,
                'results' => [],
                'error' => "Could not fetch {$site->url} to work out what kind of business this is.",
            ]);
        }

        if (! $response->successful()) {
            return view('competitors.search', [
                'site' => $site,
                'query' => null,
                'results' => [],
                'error' => "{$site->url} returned HTTP {$response->status()}, so its content could not be read.",
            ]);
        }

        $category = $claude->inferBusinessCategory($site->name, $this->extractPageText($response->body()));

        if ($category['error']) {
            return view('competitors.search', [
                'site' => $site,
                'query' => null,
                'results' => [],
                'error' => $category['error'],
            ]);
        }

        $query = $category['category'] . ' ' . $site->location;

        $result = $dataForSeo->searchByQuery($query, $site->url);

        return view('competitors.search', [
            'site' => $site,
            'query' => $query,
            'results' => $result['results'],
            'error' => $result['error'],
        ]);
    }

    /** Title, meta description, and a truncated slice of visible body
     *  text - enough for Claude to know what the business does without
     *  sending a whole page (and its cost) for what is a 2-5 word
     *  answer. Deliberately not reusing SeoAuditService's own parsing -
     *  that class's checks are about SEO correctness, this is about
     *  reading what the page actually says, a different job even
     *  though both start with a DOMDocument. */
    private function extractPageText(string $html): string
    {
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_clear_errors();

        $xpath = new \DOMXPath($dom);

        $title = $xpath->query('//title')->item(0)?->textContent ?? '';

        $description = '';
        foreach ($xpath->query('//meta[@name="description"]') as $meta) {
            $description = $meta->getAttribute('content');
            break;
        }

        $body = $xpath->query('//body')->item(0);
        $bodyText = $body ? trim(preg_replace('/\s+/', ' ', $body->textContent)) : '';

        return trim("Title: {$title}\nDescription: {$description}\nPage text: " . mb_substr($bodyText, 0, 1500));
    }

    public function search(Request $request, Site $site, DataForSeoService $dataForSeo)
    {
        $data = $request->validate([
            'query' => ['required', 'string', 'max:255'],
        ]);

        $result = $dataForSeo->searchByQuery($data['query'], $site->url);

        return view('competitors.search', [
            'site' => $site,
            'query' => $data['query'],
            'results' => $result['results'],
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
