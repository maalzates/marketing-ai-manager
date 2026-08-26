<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | Two kinds of credentials live in this application and only one of them is
    | here. Everything in this file identifies *the application* — the OAuth
    | clients and base URLs that are the same for every user of this deployment.
    |
    | Credentials that identify *a user* (their Apify key, their LLM key, their
    | Meta and Google tokens) are never in this file and never in the
    | environment: they are encrypted per account in the `integrations` table
    | and resolved at runtime. See .ai/architecture.md, "External API clients
    | and credentials".
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
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
    | Google — one OAuth client covers sign-in, Drive and YouTube.
    |
    | The consent screen must be published in Production. While it is in
    | Testing, Google expires every refresh token after 7 days and the app
    | silently loses access to every user's Drive. See SETUP.md.
    */
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        // Derived, never configured: a second source of truth for this value drifts from
        // what is registered in the Google console, and Google answers `redirect_uri_mismatch`.
        'redirect' => rtrim((string) env('APP_URL'), '/').'/api/v1/auth/google/callback',
        'auth_url' => 'https://accounts.google.com/o/oauth2/v2/auth',
        'token_url' => 'https://oauth2.googleapis.com/token',
        'revoke_url' => 'https://oauth2.googleapis.com/revoke',
        'userinfo_url' => 'https://www.googleapis.com/oauth2/v3/userinfo',
        'drive_base_url' => 'https://www.googleapis.com/drive/v3/',
        'drive_upload_url' => 'https://www.googleapis.com/upload/drive/v3/files',
        'youtube_base_url' => 'https://www.googleapis.com/youtube/v3/',
        'login_scopes' => ['openid', 'email', 'profile'],
        'drive_scopes' => ['https://www.googleapis.com/auth/drive.file'],
        'youtube_scopes' => ['https://www.googleapis.com/auth/youtube.readonly'],
        // Drive requires resumable uploads above this size.
        'resumable_upload_threshold_bytes' => 5 * 1024 * 1024,
    ],

    /*
    | Meta — one app covers Marketing API, Instagram content and Instagram
    | insights, through Facebook Login for Business.
    */
    'meta' => [
        'app_id' => env('META_APP_ID'),
        'app_secret' => env('META_APP_SECRET'),
        'redirect' => env('META_REDIRECT_URI'),
        'graph_version' => env('META_GRAPH_VERSION', 'v26.0'),
        'graph_base_url' => 'https://graph.facebook.com/',
        'auth_url' => 'https://www.facebook.com/dialog/oauth',
        'scopes' => [
            'ads_management',
            'ads_read',
            'business_management',
            'pages_show_list',
            'pages_read_engagement',
            'instagram_basic',
            'instagram_manage_insights',
            'instagram_content_publish',
        ],
    ],

    /*
    | Apify — base URL only. The token is per account (BYOK).
    */
    'apify' => [
        'base_url' => 'https://api.apify.com/v2/',
        'actors' => [
            'instagram_posts' => 'apify~instagram-scraper',
            'instagram_comments' => 'apify~instagram-comment-scraper',
            'facebook_ads' => 'apify~facebook-ads-scraper',
        ],
    ],

    /*
    | LLM providers — base URLs, required headers and prices only. Every API key
    | is per account (BYOK) and lives encrypted in `integrations`.
    |
    | Prices are USD per million tokens and feed the consumption ledger. They
    | move: they are configuration, not constants.
    */
    // Prices verified against the providers' own pricing pages on this date. They move;
    // re-check them rather than trusting the number.
    'llm_prices_verified_at' => '2026-08-23',

    'anthropic' => [
        'base_url' => 'https://api.anthropic.com/',
        'version' => '2023-06-01',
        'key_prefixes' => ['sk-ant-'],
        // input_tokens EXCLUDES cached tokens on this provider: total input is
        // input_tokens + cache_read_input_tokens + cache_creation_input_tokens.
        'cached_tokens_are_additive' => true,
        // No provider serves prices over the API, so this is the tariff and the ledger bills
        // from it. A model the provider lists and this table does not is still callable — it
        // simply records a cost of zero, which is why the pricing page is one click away.
        'pricing_url' => 'https://www.anthropic.com/pricing#api',
        'models' => [
            'claude-3-5-haiku-latest' => ['input' => 0.80, 'output' => 4.00],
            'claude-haiku-4-5' => ['input' => 1.00, 'output' => 5.00],
            'claude-sonnet-5' => ['input' => 3.00, 'output' => 15.00],
            'claude-opus-5' => ['input' => 5.00, 'output' => 25.00],
        ],
    ],

    'openai' => [
        'base_url' => 'https://api.openai.com/v1/',
        'key_prefixes' => ['sk-proj-', 'sk-svcacct-', 'sk-'],
        // prompt_tokens ALREADY INCLUDES prompt_tokens_details.cached_tokens.
        'cached_tokens_are_additive' => false,
        'pricing_url' => 'https://openai.com/api/pricing/',
        'models' => [
            'gpt-4.1-nano' => ['input' => 0.10, 'output' => 0.40],
            'gpt-4o-mini' => ['input' => 0.15, 'output' => 0.60],
            'gpt-5.6-luna' => ['input' => 0.20, 'output' => 1.20],
            'gpt-4.1-mini' => ['input' => 0.40, 'output' => 1.60],
            'gpt-4.1' => ['input' => 2.00, 'output' => 8.00],
            'gpt-4o' => ['input' => 2.50, 'output' => 10.00],
            'gpt-5.6-sol' => ['input' => 4.00, 'output' => 20.00],
        ],
    ],

    'gemini' => [
        'base_url' => 'https://generativelanguage.googleapis.com/v1beta/',
        // Google is mid-migration to `AQ.` keys; a strict ^AIza check would lock out
        // accounts issued a new-format key.
        'key_prefixes' => ['AIza', 'AQ.'],
        'cached_tokens_are_additive' => false,
        'pricing_url' => 'https://ai.google.dev/pricing',
        'models' => [
            'gemini-2.5-flash-lite' => ['input' => 0.10, 'output' => 0.40],
            'gemini-2.5-flash' => ['input' => 0.30, 'output' => 2.50],
            'gemini-3.5-flash-lite' => ['input' => 0.30, 'output' => 2.50],
            'gemini-3.7-flash' => ['input' => 0.75, 'output' => 3.75],
            'gemini-2.5-pro' => ['input' => 1.25, 'output' => 10.00],
        ],
    ],

];
