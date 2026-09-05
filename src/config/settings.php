<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Settings registry — declared defaults
|--------------------------------------------------------------------------
|
| The dotted leaves of this file are the complete set of writable setting
| keys and the last level of the cascade (strategy → account → global →
| here). A key that is not declared here cannot be stored, which is what
| keeps behaviour out of the code and inside the registry.
|
| These are behaviour defaults, not deployment secrets: nothing here reads
| env(). Per-deployment credentials live in config/services.php.
|
*/

return [

    'ai' => [
        'models' => [
            'same_for_all' => false,

            'per_task' => [
                'chat' => 'claude-sonnet-5',
                'content_script' => 'claude-sonnet-5',
                'campaign_proposal' => 'claude-sonnet-5',
                'verdict' => 'claude-sonnet-5',
                'guardian' => 'claude-sonnet-5',
                'comment_sentiment' => 'claude-haiku-4-5',
                'comment_mining' => 'claude-haiku-4-5',
                'insight_extraction' => 'claude-haiku-4-5',
                'field_suggestion' => 'claude-haiku-4-5',
            ],
        ],

        'limits' => [
            'max_output_tokens' => 4096,
            'temperature' => 0.7,
        ],

        'budget' => [
            'daily_tokens' => 1000000,
            'monthly_tokens' => 20000000,
            'alert_threshold_percent' => 80,
        ],
    ],

    'chat' => [
        // The loop bound. Each round trip is a paid call on the account's own key, so an
        // assistant that never stops asking for tools stops here instead of at the invoice.
        'max_tool_round_trips' => 5,
        'history_window_messages' => 20,
        'summary_max_tokens' => 600,
    ],

    'apify' => [
        'budget' => [
            'daily_calls' => 100,
        ],
    ],

    /*
     * Which Meta ad account the Campaign Manager writes to. Two of them, because the
     * sandbox toggle on the account does not change a flag on the call — it changes the
     * ad account every campaign, budget and insight refers to.
     */
    'campaigns' => [
        'meta_ad_account_id' => '',
        'meta_sandbox_ad_account_id' => '',
    ],

    'guardian' => [
        'enabled' => true,
        'frequency_days' => 1,
        'reports_enabled' => true,
        'anomaly_multiplier' => 3,
        'auto_skip_without_active_experiments' => true,
    ],

    'budgets' => [
        'max_monthly_per_strategy' => 5000.0,
        'max_per_experiment' => 2000.0,
    ],

    'features' => [
        'chat' => true,
        'comment_mining' => true,
        'competitor_analysis' => true,
        'campaigns' => true,
        'carousel_generator' => false,
        'tiktok' => false,
        'youtube' => false,
    ],

    'rate_limits' => [
        'default_per_minute' => 60,
        'chat_per_minute' => 10,
        'admin_per_minute' => 120,
    ],

    'retention' => [
        'scraped_data_days' => 180,
        'action_logs_days' => 365,
        'usage_logs_days' => 730,
    ],

    'notifications' => [
        'proposals' => true,
        'reports' => true,
        'token_expiry' => true,
        'usage_limits' => true,
    ],

    'preferences' => [
        'timezone' => 'UTC',
        'locale' => 'es',
    ],

    'maintenance' => [
        'mode' => false,
        'jobs_paused' => false,
    ],

];
