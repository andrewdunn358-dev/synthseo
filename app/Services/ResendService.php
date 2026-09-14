<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * A single plain transactional send via Resend's own /emails endpoint
 * - deliberately not using Resend's Audiences/Contacts/Broadcasts.
 *
 * WHY: those need a "Full access" API key, and the real account this
 * shipped against only has "Sending access" (Full access was greyed
 * out on that account, cause unconfirmed). Sending access can still
 * do exactly one thing - send a single email - which is exactly what
 * this method does. The newsletter feature is also still being tried
 * out and may not stick, so a local subscriber list plus one send
 * call per recipient (see NewsletterController::send) is the
 * simplest thing that actually works today, not a bigger
 * rearchitecture toward a permission level this account may never
 * get. If this becomes a permanent feature at real subscriber volume,
 * Resend's own Broadcasts (and their built-in unsubscribe handling)
 * are worth revisiting - this app deliberately still does not build
 * its own bulk-sending infrastructure; see the git history for why.
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
     * @return array{error:?string}
     */
    public function sendEmail(string $to, string $fromAddress, string $subject, string $htmlBody): array
    {
        if (! $this->apiKey) {
            return ['error' => 'No Resend API key configured. Add RESEND_API_KEY in .env.'];
        }

        try {
            $response = Http::withToken($this->apiKey)
                ->timeout(self::TIMEOUT)
                ->post(self::BASE . 'emails', [
                    'from' => $fromAddress,
                    'to' => $to,
                    'subject' => $subject,
                    'html' => $htmlBody,
                ]);
        } catch (\Throwable $e) {
            Log::warning('Resend send failed', ['to' => $to, 'error' => $e->getMessage()]);

            return ['error' => 'The request did not complete (it may have timed out).'];
        }

        if (! $response->successful()) {
            return ['error' => $this->readableError($response->status(), $response->json())];
        }

        return ['error' => null];
    }

    private function readableError(int $status, ?array $body): string
    {
        $message = $body['message'] ?? '';

        // 401/403 is ambiguous - Resend uses it both for a genuinely
        // bad key and for "this domain isn't verified yet", with
        // completely different fixes. Checking the message content
        // rather than trusting the status code alone is the difference
        // between telling someone to regenerate a working key and
        // correctly telling them to wait on DNS.
        if (($status === 401 || $status === 403) && stripos($message, 'not verified') !== false) {
            return 'The sending domain isn\'t verified in Resend yet (DNS records can take a while to propagate). '
                . 'Check the Domains page in Resend - sending will start working once it shows verified.';
        }

        return match (true) {
            $status === 401 || $status === 403 => 'The Resend API key was rejected. Check RESEND_API_KEY in .env.',
            $status === 422 => 'Resend rejected the request' . ($message ? ': ' . mb_substr($message, 0, 200) : '.'),
            $status === 429 => 'Resend rate-limited the request. Wait a moment and try again.',
            $status >= 500 => 'Resend is temporarily unavailable. Try again shortly.',
            default => "Resend returned HTTP {$status}.",
        };
    }
}
