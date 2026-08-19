<?php

return [
    /*
    |--------------------------------------------------------------------------
    | LLM provider configuration
    |--------------------------------------------------------------------------
    |
    | API keys are read from .env only. Never commit a real key.
    | The analytical route plans a structured read-only AST, which Laravel
    | validates deterministically before execution. The model never supplies
    | raw SQL.
    |
    */

    'provider' => env('LLM_PROVIDER', 'openai_compatible'),
    'api_key' => env('LLM_API_KEY'),
    'model' => env('LLM_MODEL', 'deepseek-v4-flash'),
    'base_url' => env('LLM_BASE_URL', 'https://api.deepseek.com'),

    // General non-streaming network budget. Provider-specific operations can
    // override this with the more focused settings below.
    'timeout' => env('LLM_TIMEOUT', 12),
    'connect_timeout' => env('LLM_CONNECT_TIMEOUT', 3),
    'stream_timeout' => env('LLM_STREAM_TIMEOUT', 60),

    // Temperature is used only in non-thinking mode. DeepSeek ignores it while
    // thinking is enabled, so normal planning/final-answer calls intentionally
    // run non-thinking and use this value.
    'temperature' => env('LLM_TEMPERATURE', 0.2),

    'planner_thinking' => env('LLM_PLANNER_THINKING', false),
    'planner_repair_thinking' => env('LLM_PLANNER_REPAIR_THINKING', true),
    'answer_thinking' => env('LLM_ANSWER_THINKING', false),
    'reasoning_effort' => env('LLM_REASONING_EFFORT', 'high'),

    'planner_timeout' => env('LLM_PLANNER_TIMEOUT', 10),
    'planner_repair_timeout' => env('LLM_PLANNER_REPAIR_TIMEOUT', 15),
    'answer_timeout' => env('LLM_ANSWER_TIMEOUT', 60),
    'request_execution_limit' => env('LLM_REQUEST_EXECUTION_LIMIT', 120),

    'planner_max_tokens' => env('LLM_PLANNER_MAX_TOKENS', 3500),
    'answer_max_tokens' => env('LLM_ANSWER_MAX_TOKENS', 1600),

    'log_questions' => env('LOG_AI_QUESTIONS', false),
    'analytical_max_execution_ms' => env('LLM_ANALYTICAL_MAX_EXECUTION_MS', 3000),

    /*
    | Legacy fallback only.
    | This list is not a capability gate for the schema-aware analytical agent.
    */
    'allowed_intents' => [
        'low_stock_products',
        'expiring_batches',
        'expired_batches',
        'today_sales',
        'monthly_sales',
        'sales_between_dates',
        'top_selling_products',
        'purchase_summary',
        'inventory_summary',
        'profit_loss_summary',
        'supplier_summary',
        'product_lookup',
        'supplier_lookup',
        'staff_lookup',
        'category_lookup',
        'stock_movements_summary',
        'unknown',
    ],
];
