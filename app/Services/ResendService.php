<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Newsletter infrastructure via Resend - audiences (one per site,
 * created lazily), contacts (subscribers), and broadcasts (the send).
 *
 * WHY RESEND OWNS THE SUBSCRIBER LIST, NOT THIS APP
 * No subscriber email address is ever stored in this app's own
 * database - every add/list operation is a live call to Resend's API.
 * Two reasons: Resend already handles the genuinely hard, compliance-
 * critical parts of this correctly (unsubscribe flows meeting Gmail/
 * Yahoo's 2024 bulk-sender requirements, suppression on unsubscribe),
 * and a second local copy of the same list would drift out of sync
 * with the real source of truth the moment someone unsubscribes
 * through Resend directly. One system of record for a client's PII
 * is also simply the safer choice.
 *
 * WHY THIS APP DOES NOT BUILD ITS OWN SENDING INFRASTRUCTURE
 * The hard part of email marketing is deliverability - IP reputation,
 * DKIM/SPF/DMARC alignment, bounce and complaint handling, staying off
 * blocklists - built by dedicated teams over years. This service is a
 * thin layer over infrastructure that already solves that; building a
 * Mailchimp/Brevo equivalent from scratch would mean months of work
 * that ends with worse deliverability than a service that already has
 * an established sending reputation.
 *
 * FAILURE IS NORMAL AND MUST NOT THROW - same shape as every other
 * service in this app.
 */
class ResendService
{
    private const BASE = 'https://api.resend.com/';

    private const TIMEOUT = 20;

    public function __construct(private ?string $apiKey = null)
    {
        $this->apiKey = $apiKey ?? config('services.resend.key');
    }

    /**
     * @return array{audience_id:?string,error:?string}
     */
    public function createAudience(string $name): array
    {
        $empty = ['audience_id' => null, 'error' => null];

        if (! $this->apiKey) {
            return array_merge($empty, [
                'error' => 'No Resend API key configured. Add RESEND_API_KEY in .env.',
            ]);
        }

        $result = $this->call('POST', 'audiences', ['name' => $name]);

        if ($result['error']) {
            return array_merge($empty, ['error' => $result['error']]);
        }

        $id = $result['body']['id'] ?? null;

        return $id
            ? ['audience_id' => $id, 'error' => null]
            : array_merge($empty, ['error' => 'Resend did not return an audience id.']);
    }

    /**
     * @return array{error:?string}
     */
    public function addSubscriber(string $audienceId, string $email, ?string $name = null): array
    {
        if (! $this->apiKey) {
            return ['error' => 'No Resend API key configured. Add RESEND_API_KEY in .env.'];
        }

        $payload = ['email' => $email];

        if ($name) {
            $payload['first_name'] = $name;
        }

        $result = $this->call('POST', "audiences/{$audienceId}/contacts", $payload);

        return ['error' => $result['error']];
    }

    /**
     * Creates and sends in a single call. `send: true` is Resend's
     * newer combined create-and-send - preferred over the older two-
     * request create-then-send flow because it removes a window where
     * a created-but-unsent draft could be left behind by a failure
     * between the two calls.
     *
     * The RESEND_UNSUBSCRIBE_URL placeholder in $htmlBody gets replaced
     * per-recipient automatically - see the class doc for why that
     * matters. Callers must include it; this method does not add it
     * silently, so it stays visible in the actual template being sent
     * rather than hidden inside this service.
     *
     * @return array{broadcast_id:?string,error:?string}
     */
    public function sendBroadcast(string $audienceId, string $fromAddress, string $subject, string $htmlBody): array
    {
        $empty = ['broadcast_id' => null, 'error' => null];

        if (! $this->apiKey) {
            return array_merge($empty, [
                'error' => 'No Resend API key configured. Add RESEND_API_KEY in .env.',
            ]);
        }

        $result = $this->call('POST', 'broadcasts', [
            'audience_id' => $audienceId,
            'from' => $fromAddress,
            'subject' => $subject,
            'html' => $htmlBody,
            'send' => true,
        ]);

        if ($result['error']) {
            return array_merge($empty, ['error' => $result['error']]);
        }

        $id = $result['body']['id'] ?? null;

        return $id
            ? ['broadcast_id' => $id, 'error' => null]
            : array_merge($empty, ['error' => 'Resend did not return a broadcast id.']);
    }

    /**
     * @return array{body:?array,error:?string}
     */
    private function call(string $method, string $path, array $payload): array
    {
        try {
            $response = Http::withToken($this->apiKey)
                ->timeout(self::TIMEOUT)
                ->{strtolower($method)}(self::BASE . $path, $payload);
        } catch (\Throwable $e) {
            Log::warning('Resend request failed', ['path' => $path, 'error' => $e->getMessage()]);

            return ['body' => null, 'error' => 'The request did not complete (it may have timed out).'];
        }

        if (! $response->successful()) {
            return ['body' => null, 'error' => $this->readableError($response->status(), $response->json())];
        }

        return ['body' => $response->json(), 'error' => null];
    }

    private function readableError(int $status, ?array $body): string
    {
        $message = $body['message'] ?? '';

        return match (true) {
            $status === 401 || $status === 403 => 'The Resend API key was rejected. Check RESEND_API_KEY in .env.',
            $status === 422 => 'Resend rejected the request' . ($message ? ': ' . mb_substr($message, 0, 200) : '.'),
            $status === 429 => 'Resend rate-limited the request. Wait a moment and try again.',
            $status >= 500 => 'Resend is temporarily unavailable. Try again shortly.',
            default => "Resend returned HTTP {$status}.",
        };
    }
}
