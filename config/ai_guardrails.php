<?php

return [
    /*
    |--------------------------------------------------------------------------
    | AI Guardrails Configuration
    |--------------------------------------------------------------------------
    |
    | Limits and guardrails for AI-driven financial operations.
    | These guardrails ensure that AI agents do not propose extreme
    | changes that could be harmful to campaigns.
    |
    */

    'financial' => [
        // Maximum allowed percentage increase (e.g., 50 means 50%)
        'max_increase_percent' => env('AI_GUARDRAIL_MAX_INCREASE_PERCENT', 50),

        // Maximum allowed percentage decrease (e.g., 90 means 90%)
        'max_decrease_percent' => env('AI_GUARDRAIL_MAX_DECREASE_PERCENT', 90),

        // Absolute maximum budget per day in local currency (normalized)
        'max_daily_budget_absolute' => env('AI_GUARDRAIL_MAX_DAILY_BUDGET', 5000),

        // Absolute maximum lifetime budget in local currency (normalized)
        'max_lifetime_budget_absolute' => env('AI_GUARDRAIL_MAX_LIFETIME_BUDGET', 50000),
    ],
];
