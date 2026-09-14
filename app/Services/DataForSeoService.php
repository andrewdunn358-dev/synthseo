<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Pulls organic search traffic/keyword metrics for a domain via
 * DataForSEO Labs' domain_rank_overview endpoint, and compares two
 * domains side by side.
 *
 * SANDBOX BY DEFAULT
 * config('services.dataforseo.sandbox') defaults to true - a request
 * against sandbox.dataforseo.com costs nothing and returns dummy data
 * in the real response shape. Live data requires deliberately setting
 * DATAFORSEO_SANDBOX=false in .env once the account is funded. Fails
 * toward "costs nothing" rather than toward "spends money", the same
 * direction every other fail-safe default in this app leans.
 *
 * FAILURE IS NORMAL AND MUST NOT THROW - same reasoning as every other
 * service in this app: a bad domain, a credentials mistake, a rate
 * limit are Tuesday, not an exception. Every public method returns an
 * error string and null data rather than throwing.
 */
class DataForSeoService
{
    private const LIVE_BASE = 'https://api.dataforseo.com/v3/';

    private const SANDBOX_BASE = 'https://sandbox.dataforseo.com/v3/';

    private const ENDPOINT = 'dataforseo_labs/google/domain_rank_overview/live';

    private const COMPETITORS_ENDPOINT = 'dataforseo_labs/google/competitors_domain/live';

    private const SERP_ENDPOINT = 'serp/google/organic/live/advanced';

    private const TIMEOUT = 30;

    public function __construct(
        private ?string $login = null,
        private ?string $password = null,
    ) {
        $this->login = $login ?? config('services.dataforseo.login');
        $this->password = $password ?? config('services.dataforseo.password');
    }

    /**
     * @return array{our_traffic:?int,our_keywords:?int,competitor_traffic:?int,competitor_keywords:?int,error:?string}
     */
    public function compareDomains(string $ourDomain, string $competitorDomain): array
    {
        $empty = [
            'our_traffic' => null, 'our_keywords' => null,
            'competitor_traffic' => null, 'competitor_keywords' => null,
            'error' => null,
        ];

        if (! $this->login || ! $this->password) {
            return array_merge($empty, [
                'error' => 'No DataForSEO credentials configured. Add DATAFORSEO_LOGIN and DATAFORSEO_PASSWORD in .env.',
            ]);
        }

        $our = $this->fetchDomainMetrics($ourDomain);

        if ($our['error']) {
            return array_merge($empty, ['error' => "Could not fetch data for {$ourDomain}: {$our['error']}"]);
        }

        $competitor = $this->fetchDomainMetrics($competitorDomain);

        if ($competitor['error']) {
            return array_merge($empty, ['error' => "Could not fetch data for {$competitorDomain}: {$competitor['error']}"]);
        }

        return [
            'our_traffic' => $our['traffic'],
            'our_keywords' => $our['keywords'],
            'competitor_traffic' => $competitor['traffic'],
            'competitor_keywords' => $competitor['keywords'],
            'error' => null,
        ];
    }

    /**
     * @return array{traffic:?int,keywords:?int,error:?string}
     */
    private function fetchDomainMetrics(string $domain): array
    {
        $domain = self::normalizeDomain($domain);
        $base = config('services.dataforseo.sandbox', true) ? self::SANDBOX_BASE : self::LIVE_BASE;

        try {
            $response = Http::withBasicAuth($this->login, $this->password)
                ->timeout(self::TIMEOUT)
                ->post($base . self::ENDPOINT, [
                    ['target' => $domain, 'location_name' => 'United Kingdom', 'language_code' => 'en'],
                ]);
        } catch (\Throwable $e) {
            Log::warning('DataForSEO request failed', ['domain' => $domain, 'error' => $e->getMessage()]);

            return ['traffic' => null, 'keywords' => null, 'error' => 'The request did not complete (it may have timed out).'];
        }

        if (! $response->successful()) {
            return ['traffic' => null, 'keywords' => null, 'error' => $this->readableError($response->status())];
        }

        $task = $response->json('tasks.0');

        if (! $task || ($task['status_code'] ?? null) !== 20000) {
            return ['traffic' => null, 'keywords' => null, 'error' => $task['status_message'] ?? 'DataForSEO returned an error.'];
        }

        $item = $task['result'][0]['items'][0] ?? null;

        if (! $item) {
            return ['traffic' => null, 'keywords' => null, 'error' => 'No data available for this domain.'];
        }

        $organic = $item['metrics']['organic'] ?? [];

        return [
            'traffic' => isset($organic['etv']) ? (int) round($organic['etv']) : null,
            'keywords' => $organic['count'] ?? null,
            'error' => null,
        ];
    }

    /**
     * Strips scheme, www, and any path - DataForSEO wants a bare
     * domain, and a client typing a full URL into the form should
     * not have to know that. Public and static so the controller can
     * clean the value before storing it too - the display should
     * show the same domain the API was actually asked about, not
     * whatever raw string someone pasted in.
     */
    public static function normalizeDomain(string $domain): string
    {
        $domain = trim($domain);
        $domain = preg_replace('#^https?://#i', '', $domain);
        $domain = preg_replace('#^www\.#i', '', $domain);

        return rtrim(explode('/', $domain)[0], '/');
    }

    /**
     * Finds domains competing with $domain for the same organic
     * keywords - the actual "who is this business up against" answer,
     * rather than requiring someone to already know a competitor's
     * name before this feature does anything.
     *
     * Synchronous rather than queued - unlike the audit/comparison
     * jobs, this is a "browse a few options and pick one" interaction,
     * not a result someone returns to later. Waiting a few seconds for
     * suggestions is the expected shape of that interaction; the queue
     * machinery would just add a page reload in the way of it.
     *
     * exclude_top_domains=true drops Wikipedia/Amazon/Google/etc. from
     * results - technically "competitors" by keyword overlap, never
     * useful ones for a local business trying to find who it is
     * actually up against.
     *
     * @return array{domains:array<int,array{domain:string,traffic:?int,keywords:?int,intersections:?int}>,error:?string}
     */
    public function findCompetitors(string $domain, int $limit = 5): array
    {
        $empty = ['domains' => [], 'error' => null];

        if (! $this->login || ! $this->password) {
            return array_merge($empty, [
                'error' => 'No DataForSEO credentials configured. Add DATAFORSEO_LOGIN and DATAFORSEO_PASSWORD in .env.',
            ]);
        }

        $target = self::normalizeDomain($domain);
        $base = config('services.dataforseo.sandbox', true) ? self::SANDBOX_BASE : self::LIVE_BASE;

        try {
            $response = Http::withBasicAuth($this->login, $this->password)
                ->timeout(self::TIMEOUT)
                ->post($base . self::COMPETITORS_ENDPOINT, [
                    [
                        'target' => $target,
                        'location_name' => 'United Kingdom',
                        'language_name' => 'English',
                        'limit' => $limit,
                        'exclude_top_domains' => true,
                    ],
                ]);
        } catch (\Throwable $e) {
            Log::warning('DataForSEO competitor discovery failed', ['domain' => $target, 'error' => $e->getMessage()]);

            return array_merge($empty, ['error' => 'The request did not complete (it may have timed out).']);
        }

        if (! $response->successful()) {
            return array_merge($empty, ['error' => $this->readableError($response->status())]);
        }

        $task = $response->json('tasks.0');

        if (! $task || ($task['status_code'] ?? null) !== 20000) {
            return array_merge($empty, ['error' => $task['status_message'] ?? 'DataForSEO returned an error.']);
        }

        $items = $task['result'][0]['items'] ?? [];

        $domains = array_values(array_filter(array_map(function (array $item) use ($target) {
            if (! isset($item['domain'])) {
                return null;
            }

            // DataForSEO can include the target itself in results (it
            // trivially "overlaps" with all its own keywords) - never
            // a competitor of itself, so filtered out here rather than
            // trusting the API to exclude what should be obvious.
            if (strcasecmp($item['domain'], $target) === 0) {
                return null;
            }

            $organic = $item['full_domain_metrics']['organic'] ?? [];

            return [
                'domain' => $item['domain'],
                'traffic' => isset($organic['etv']) ? (int) round($organic['etv']) : null,
                'keywords' => $organic['count'] ?? null,
                'intersections' => $item['intersections'] ?? null,
            ];
        }, $items)));

        return ['domains' => $domains, 'error' => null];
    }

    /**
     * Finds who currently ranks in real Google results for a specific
     * search phrase - directly answers "who shows up when a customer
     * actually searches this," rather than depending on the target
     * site's own keyword history the way findCompetitors() does.
     *
     * This is the fix for the "garbage in, garbage out" problem a
     * low-traffic site hits with keyword-overlap discovery: a site
     * with almost no rankings of its own has nothing for that
     * algorithm to work from, but a live search for "IT support North
     * Shields" works identically regardless of how established the
     * client's own site is - it queries Google directly, not the
     * client's footprint.
     *
     * @return array{results:array<int,array{domain:string,title:string,rank:int}>,error:?string}
     */
    public function searchByQuery(string $query, ?string $excludeDomain = null): array
    {
        $empty = ['results' => [], 'error' => null];

        if (! $this->login || ! $this->password) {
            return array_merge($empty, [
                'error' => 'No DataForSEO credentials configured. Add DATAFORSEO_LOGIN and DATAFORSEO_PASSWORD in .env.',
            ]);
        }

        $base = config('services.dataforseo.sandbox', true) ? self::SANDBOX_BASE : self::LIVE_BASE;

        try {
            $response = Http::withBasicAuth($this->login, $this->password)
                ->timeout(self::TIMEOUT)
                ->post($base . self::SERP_ENDPOINT, [
                    [
                        'keyword' => $query,
                        'location_name' => 'United Kingdom',
                        'language_code' => 'en',
                        'device' => 'desktop',
                    ],
                ]);
        } catch (\Throwable $e) {
            Log::warning('DataForSEO SERP search failed', ['query' => $query, 'error' => $e->getMessage()]);

            return array_merge($empty, ['error' => 'The request did not complete (it may have timed out).']);
        }

        if (! $response->successful()) {
            return array_merge($empty, ['error' => $this->readableError($response->status())]);
        }

        $task = $response->json('tasks.0');

        if (! $task || ($task['status_code'] ?? null) !== 20000) {
            return array_merge($empty, ['error' => $task['status_message'] ?? 'DataForSEO returned an error.']);
        }

        $items = $task['result'][0]['items'] ?? [];
        $excludeDomain = $excludeDomain ? self::normalizeDomain($excludeDomain) : null;

        // Google's SERP mixes organic results with ads, People Also
        // Ask, featured snippets, etc. in one items array - only
        // "organic" is a real competitor ranking, everything else is
        // noise for this purpose. Deduped by domain since the same
        // site can legitimately hold more than one organic position.
        $seen = [];
        $results = [];

        foreach ($items as $item) {
            if (($item['type'] ?? null) !== 'organic' || ! isset($item['domain'])) {
                continue;
            }

            $domain = $item['domain'];

            if ($excludeDomain && strcasecmp($domain, $excludeDomain) === 0) {
                continue;
            }

            if (isset($seen[$domain])) {
                continue;
            }

            $seen[$domain] = true;

            $results[] = [
                'domain' => $domain,
                'title' => $item['title'] ?? $domain,
                'rank' => $item['rank_absolute'] ?? count($results) + 1,
            ];

            if (count($results) >= 10) {
                break;
            }
        }

        return ['results' => $results, 'error' => null];
    }

    private function readableError(int $status): string
    {
        return match (true) {
            $status === 401 => 'DataForSEO credentials were rejected. Check DATAFORSEO_LOGIN/DATAFORSEO_PASSWORD in .env.',
            $status === 402 => 'DataForSEO account balance is too low for this request.',
            $status === 429 => 'DataForSEO rate-limited the request. Wait a moment and try again.',
            $status >= 500 => 'DataForSEO is temporarily unavailable. Try again shortly.',
            default => "DataForSEO returned HTTP {$status}.",
        };
    }
}
