<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    /*
     | Google PageSpeed Insights. Optional - PSI answers without a key
     | but rate-limits hard, which is fine for testing and not for
     | running audits across a client base. Get one from the Google
     | Cloud console with the PageSpeed Insights API enabled.
     */
    'pagespeed' => [
        'key' => env('PAGESPEED_API_KEY'),
    ],

    /*
     | Anthropic Claude API. Used for content-generation drafts only -
     | this is an API key from console.anthropic.com, billed per use,
     | not a ChatGPT-style subscription. Without it, generation requests
     | fail with a readable error rather than throwing.
     */
    'anthropic' => [
        'key' => env('ANTHROPIC_API_KEY'),
    ],

    /*
     | DataForSEO Labs API - used for competitor traffic/keyword
     | comparison only. `sandbox` defaults to true: sandbox requests
     | cost nothing and return dummy data in the real response shape,
     | so a missing or wrong .env value fails toward "free and fake"
     | rather than toward "spends real money". Set
     | DATAFORSEO_SANDBOX=false only once the account is funded and
     | the integration has been proven against sandbox data.
     */
    'dataforseo' => [
        'login' => env('DATAFORSEO_LOGIN'),
        'password' => env('DATAFORSEO_PASSWORD'),
        'sandbox' => env('DATAFORSEO_SANDBOX', true),
    ],

    /*
     | OpenAI - used for social post image generation only (gpt-image-1).
     | Separate account/key from Anthropic - Claude has no image
     | generation, this is a different vendor for a different job.
     */
    'openai' => [
        'key' => env('OPENAI_API_KEY'),
    ],

    /*
     | Used two ways: Laravel's own Mail facade (MAIL_MAILER=resend)
     | for transactional email, and ResendService's direct REST calls
     | for newsletter audiences/broadcasts - same API key, same
     | account, two different uses. `from_address` needs its domain
     | verified in Resend before broadcasts will actually send.
     */
    'resend' => [
        'key' => env('RESEND_API_KEY'),
        'from_address' => env('RESEND_FROM_ADDRESS', 'newsletter@synthseo.co.uk'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
     | Registration invite code. There's nothing to leak by opening
     | registration - every signup gets its own new, isolated tenant
     | (see AuthController::register) - but this is an agency-managed
     | product, not a public self-serve signup, so a code gates it
     | until there's an actual invite-link flow. If unset, registration
     | fails closed rather than silently accepting anything: no code
     | configured means no code can match.
     */
    'registration' => [
        'code' => env('REGISTRATION_CODE'),
    ],

];
