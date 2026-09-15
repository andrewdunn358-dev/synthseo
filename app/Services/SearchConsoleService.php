<?php

namespace App\Services;

use App\Models\Site;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Google Search Console - the client's own real search data (actual
 * impressions, clicks, and average position) rather than the
 * third-party estimates DataForSeoService provides.
 *
 * WHY THIS IS WORTH THE OAUTH COMPLEXITY
 * Every other data source in this app is an outside estimate. This is
 * the only one that reports what Google itself recorded: which
 * queries actually showed this site, how often someone clicked, and
 * where it actually sat. It's also free - no per-call cost, unlike
 * DataForSEO - and it answers "which keywords are even worth
 * tracking" with evidence instead of guesswork.
 *
 * OAUTH, NOT A SERVICE ACCOUNT
 * Search Console has no usable service-account path: access is granted
 * per Google user, so it has to be a real consent flow with a refresh
 * token stored afterwards. Access tokens last an hour; refresh tokens
 * last until revoked, which is why they're stored encrypted (see the
 * migration's doc comment).
 *
 * FAILURE IS NORMAL AND MUST NOT THROW - same shape as every other
 * service in this app.
 */
class SearchConsoleService
{
    private const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';

    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    private const API_BASE = 'https://www.googleapis.com/webmasters/v3/';

    /** Read-only deliberately - this app never needs to change
     *  anything in a client's Search Console, and asking for write
     *  access would be asking a client to grant more than the job
     *  requires. */
    private const SCOPE = 'https://www.googleapis.com/auth/webmasters.readonly';

    private const TIMEOUT = 30;

    public function isConfigured(): bool
    {
        return (bool) (config('services.google.client_id') && config('services.google.client_secret'));
    }

    /**
     * Where to send someone to grant access. `state` carries the site
     * id so the callback knows which site it's completing - there's no
     * other reliable way to tell, since Google redirects to one fixed
     * URI for the whole app.
     *
     * access_type=offline and prompt=consent together are what
     * actually produce a refresh token: without offline there is no
     * refresh token at all, and without the forced consent Google
     * silently omits it on any re-authorisation, which looks like it
     * worked right up until the first token expiry an hour later.
     */
    public function authUrl(Site $site): string
    {
        return self::AUTH_URL . '?' . http_build_query([
            'client_id' => config('services.google.client_id'),
            'redirect_uri' => config('services.google.redirect'),
            'response_type' => 'code',
            'scope' => self::SCOPE,
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $site->id,
        ]);
    }

    /**
     * @return array{error:?string}
     */
    public function exchangeCode(Site $site, string $code): array
    {
        try {
            $response = Http::timeout(self::TIMEOUT)->asForm()->post(self::TOKEN_URL, [
                'code' => $code,
                'client_id' => config('services.google.client_id'),
                'client_secret' => config('services.google.client_secret'),
                'redirect_uri' => config('services.google.redirect'),
                'grant_type' => 'authorization_code',
            ]);
        } catch (\Throwable $e) {
            Log::warning('Search Console token exchange failed', ['site' => $site->id, 'error' => $e->getMessage()]);

            return ['error' => 'The request to Google did not complete.'];
        }

        if (! $response->successful()) {
            return ['error' => $this->readableError($response->status(), $response->json())];
        }

        $body = $response->json();

        if (empty($body['refresh_token'])) {
            return ['error' => 'Google did not return a refresh token. Disconnect the app at '
                . 'myaccount.google.com/permissions and connect again.'];
        }

        $site->update([
            'gsc_access_token' => $body['access_token'] ?? null,
            'gsc_refresh_token' => $body['refresh_token'],
            'gsc_token_expires_at' => now()->addSeconds($body['expires_in'] ?? 3600),
        ]);

        return ['error' => null];
    }

    /**
     * Returns a usable access token, refreshing it first if it has
     * expired or is about to. The 60-second margin avoids the case
     * where a token passes this check and then expires mid-request.
     *
     * @return array{token:?string,error:?string}
     */
    private function accessToken(Site $site): array
    {
        if (! $site->gsc_refresh_token) {
            return ['token' => null, 'error' => 'This site is not connected to Search Console.'];
        }

        if ($site->gsc_access_token && $site->gsc_token_expires_at?->isAfter(now()->addSeconds(60))) {
            return ['token' => $site->gsc_access_token, 'error' => null];
        }

        try {
            $response = Http::timeout(self::TIMEOUT)->asForm()->post(self::TOKEN_URL, [
                'refresh_token' => $site->gsc_refresh_token,
                'client_id' => config('services.google.client_id'),
                'client_secret' => config('services.google.client_secret'),
                'grant_type' => 'refresh_token',
            ]);
        } catch (\Throwable $e) {
            Log::warning('Search Console token refresh failed', ['site' => $site->id, 'error' => $e->getMessage()]);

            return ['token' => null, 'error' => 'The request to Google did not complete.'];
        }

        if (! $response->successful()) {
            return ['token' => null, 'error' => $this->readableError($response->status(), $response->json())];
        }

        $body = $response->json();

        $site->update([
            'gsc_access_token' => $body['access_token'] ?? null,
            'gsc_token_expires_at' => now()->addSeconds($body['expires_in'] ?? 3600),
        ]);

        return ['token' => $body['access_token'] ?? null, 'error' => null];
    }

    /**
     * The properties this login can actually see. Asked rather than
     * guessed because Google's property identifiers don't follow from
     * a site URL - see the migration's doc comment.
     *
     * @return array{properties:array<int,string>,error:?string}
     */
    public function listProperties(Site $site): array
    {
        $auth = $this->accessToken($site);

        if ($auth['error']) {
            return ['properties' => [], 'error' => $auth['error']];
        }

        try {
            $response = Http::withToken($auth['token'])->timeout(self::TIMEOUT)->get(self::API_BASE . 'sites');
        } catch (\Throwable $e) {
            return ['properties' => [], 'error' => 'The request to Google did not complete.'];
        }

        if (! $response->successful()) {
            return ['properties' => [], 'error' => $this->readableError($response->status(), $response->json())];
        }

        $entries = $response->json('siteEntry') ?? [];

        // Properties where this login is only a restricted user can't
        // read search analytics, so listing them would offer choices
        // that then fail on selection.
        $properties = array_values(array_map(
            fn ($entry) => $entry['siteUrl'],
            array_filter($entries, fn ($entry) => ($entry['permissionLevel'] ?? null) !== 'siteUnverifiedUser'),
        ));

        return ['properties' => $properties, 'error' => null];
    }

    /**
     * Does this property plausibly cover this site?
     *
     * Not a strict string match, because Google's identifiers and a
     * site URL are different shapes: "sc-domain:example.co.uk",
     * "https://example.co.uk/" and "https://www.example.co.uk" can all
     * legitimately be the same site. A sc-domain property also covers
     * every subdomain, so a site at blog.example.co.uk genuinely
     * matches a property for example.co.uk - which is exactly why a
     * mismatch is worth warning about rather than hard-blocking.
     */
    public static function propertyMatchesSite(string $property, Site $site): bool
    {
        $host = parse_url($site->url, PHP_URL_HOST);

        if (! $host) {
            return false;
        }

        $host = preg_replace('/^www\./i', '', strtolower($host));

        $normalised = strtolower($property);
        $normalised = preg_replace('#^sc-domain:#', '', $normalised);
        $normalised = preg_replace('#^https?://#', '', $normalised);
        $normalised = preg_replace('#^www\.#', '', $normalised);
        $normalised = rtrim($normalised, '/');

        // Exact match, or the property is a parent domain of this site.
        return $normalised === $host || str_ends_with($host, '.' . $normalised);
    }

    /**
     * Top search queries for the last $days days.
     *
     * Ends three days ago, not today: Search Console data lags by
     * roughly two to three days, so a range ending today reliably
     * returns a partial final day or nothing at all for it, which
     * reads as a sudden unexplained drop.
     *
     * @return array{rows:array<int,array{query:string,clicks:int,impressions:int,ctr:float,position:float}>,totals:?array,error:?string}
     */
    public function topQueries(Site $site, int $days = 28, int $limit = 25): array
    {
        $empty = ['rows' => [], 'totals' => null, 'error' => null];

        if (! $site->gsc_property) {
            return array_merge($empty, ['error' => 'No Search Console property selected for this site.']);
        }

        $auth = $this->accessToken($site);

        if ($auth['error']) {
            return array_merge($empty, ['error' => $auth['error']]);
        }

        $end = now()->subDays(3)->toDateString();
        $start = now()->subDays(3 + $days)->toDateString();

        $endpoint = self::API_BASE . 'sites/' . rawurlencode($site->gsc_property) . '/searchAnalytics/query';

        try {
            $response = Http::withToken($auth['token'])->timeout(self::TIMEOUT)->post($endpoint, [
                'startDate' => $start,
                'endDate' => $end,
                'dimensions' => ['query'],
                'rowLimit' => $limit,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Search Console query failed', ['site' => $site->id, 'error' => $e->getMessage()]);

            return array_merge($empty, ['error' => 'The request to Google did not complete.']);
        }

        if (! $response->successful()) {
            return array_merge($empty, ['error' => $this->readableError($response->status(), $response->json())]);
        }

        $rows = array_map(fn ($row) => [
            'query' => $row['keys'][0] ?? '',
            'clicks' => (int) ($row['clicks'] ?? 0),
            'impressions' => (int) ($row['impressions'] ?? 0),
            'ctr' => (float) ($row['ctr'] ?? 0),
            'position' => (float) ($row['position'] ?? 0),
        ], $response->json('rows') ?? []);

        // Totals summed from the returned rows rather than a second
        // API call. Honest about what it is: the total across the top
        // $limit queries, not the site's entire search traffic - the
        // view labels it that way rather than implying otherwise.
        $totals = [
            'clicks' => array_sum(array_column($rows, 'clicks')),
            'impressions' => array_sum(array_column($rows, 'impressions')),
            'start' => $start,
            'end' => $end,
        ];

        return ['rows' => $rows, 'totals' => $totals, 'error' => null];
    }

    private function readableError(int $status, ?array $body): string
    {
        $message = $body['error']['message'] ?? ($body['error_description'] ?? '');

        return match (true) {
            $status === 401 => 'Google rejected the stored credentials. Disconnect and reconnect this site.',
            $status === 403 => 'This Google login does not have access to that Search Console property'
                . ($message ? ': ' . mb_substr($message, 0, 160) : '.'),
            $status === 429 => 'Google rate-limited the request. Try again shortly.',
            $status >= 500 => 'Google is temporarily unavailable. Try again shortly.',
            default => 'Google returned HTTP ' . $status . ($message ? ': ' . mb_substr($message, 0, 160) : '.'),
        };
    }
}
