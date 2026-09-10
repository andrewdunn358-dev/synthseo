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
        $domain = $this->normalizeDomain($domain);
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

    /** Strips scheme, www, and any path - DataForSEO wants a bare
     *  domain, and a client typing a full URL into the form should
     *  not have to know that. */
    private function normalizeDomain(string $domain): string
    {
        $domain = trim($domain);
        $domain = preg_replace('#^https?://#i', '', $domain);
        $domain = preg_replace('#^www\.#i', '', $domain);

        return rtrim(explode('/', $domain)[0], '/');
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
