<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Generates one image via OpenAI's gpt-image-1 and saves it to disk.
 *
 * SAVED TO public/, NOT LARAVEL'S STORAGE DISK
 * This app has never set up the storage:link symlink shared hosting
 * needs for Storage::url() to work, and every other generated/static
 * asset (logo, favicon) already lives directly under public/ and is
 * referenced with asset() - matching that existing convention is
 * simpler than introducing a second, inconsistent way of serving
 * files for this one feature.
 *
 * BASE64, NOT A URL
 * gpt-image-1 returns base64 by default (unlike dall-e-3, which
 * defaults to a URL). That is the right default for this anyway - a
 * URL-format image expires in about an hour, so relying on it would
 * mean a client's saved social post silently loses its image link the
 * next day. Base64 is decoded and written to a permanent local file
 * immediately, so the image still exists whenever someone opens the
 * post later.
 *
 * FAILURE IS NORMAL AND MUST NOT THROW - same shape as every other
 * service in this app. A caption can succeed while an image fails (or
 * the reverse); see the social_posts migration's doc comment for why
 * that is tracked as two independent outcomes, not one.
 */
class OpenAiImageService
{
    private const ENDPOINT = 'https://api.openai.com/v1/images/generations';

    private const MODEL = 'gpt-image-1';

    /** Image generation genuinely takes 20-60s, sometimes longer under
     *  load - generous but bounded so a stuck request doesn't run out
     *  the clock on the job's overall timeout. */
    private const TIMEOUT = 90;

    public function __construct(private ?string $apiKey = null)
    {
        $this->apiKey = $apiKey ?? config('services.openai.key');
    }

    /**
     * @return array{path:?string,error:?string}
     */
    public function generateImage(string $prompt): array
    {
        $empty = ['path' => null, 'error' => null];

        if (! $this->apiKey) {
            return array_merge($empty, [
                'error' => 'No OpenAI API key configured. Add OPENAI_API_KEY in .env to enable image generation.',
            ]);
        }

        try {
            $response = Http::withToken($this->apiKey)
                ->timeout(self::TIMEOUT)
                ->post(self::ENDPOINT, [
                    'model' => self::MODEL,
                    'prompt' => $prompt,
                    'size' => '1024x1024',
                    'n' => 1,
                ]);
        } catch (\Throwable $e) {
            Log::warning('OpenAI image request failed', ['error' => $e->getMessage()]);

            return array_merge($empty, ['error' => 'The image request did not complete (it may have timed out).']);
        }

        if (! $response->successful()) {
            return array_merge($empty, ['error' => $this->readableError($response->status(), $response->json())]);
        }

        $b64 = $response->json('data.0.b64_json');

        if (! is_string($b64) || $b64 === '') {
            return array_merge($empty, ['error' => 'OpenAI returned no image data.']);
        }

        $bytes = base64_decode($b64, true);

        if ($bytes === false) {
            return array_merge($empty, ['error' => 'The image data returned could not be decoded.']);
        }

        $relativePath = 'social-images/' . Str::uuid() . '.png';
        $fullPath = public_path($relativePath);

        if (! is_dir(dirname($fullPath))) {
            mkdir(dirname($fullPath), 0755, true);
        }

        file_put_contents($fullPath, $bytes);

        return ['path' => $relativePath, 'error' => null];
    }

    private function readableError(int $status, ?array $body): string
    {
        $message = $body['error']['message'] ?? '';

        return match (true) {
            $status === 401 => 'The OpenAI API key was rejected. Check OPENAI_API_KEY in .env.',
            $status === 429 => 'OpenAI rate-limited the request, or the account has run out of credit. Check your OpenAI billing.',
            $status === 400 => 'OpenAI rejected the request' . ($message ? ': ' . mb_substr($message, 0, 200) : '.'),
            $status >= 500 => 'OpenAI is temporarily unavailable. Try again shortly.',
            default => "OpenAI returned HTTP {$status}.",
        };
    }
}
