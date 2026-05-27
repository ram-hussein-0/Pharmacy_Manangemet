<?php

return [
    /*
    |--------------------------------------------------------------------------
    | LLM provider configuration for the AI Database Assistant.
    |--------------------------------------------------------------------------
    |
    | API keys are read from .env only. Never commit a real key.
    | The assistant must remain Intent-Classifier-only and must never
    | generate raw SQL from user input.
    |
    */

    'provider' => env('LLM_PROVIDER', 'gemini'),
    'api_key' => env('LLM_API_KEY'),
    'model' => env('LLM_MODEL', 'gemini-2.0-flash'),

    'log_questions' => env('LOG_AI_QUESTIONS', false),

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
        'stock_movements_summary',
        'unknown',
    ],
];
