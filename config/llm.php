<?php

return [
    /*
    |--------------------------------------------------------------------------
    | LLM provider configuration for the AI Database Assistant.
    |--------------------------------------------------------------------------
    |
    | API keys are read from .env only. Never commit a real key.
    |
    | Supported provider modes:
    |
    | - gemini
    |   Uses Google Gemini REST generateContent endpoint.
    |
    | - openai_compatible
    |   Uses a generic OpenAI-compatible /chat/completions endpoint.
    |   This can be used with providers such as xAI/Grok, OpenRouter,
    |   DeepSeek, Together, local OpenAI-compatible gateways, etc.
    |
    | The assistant must remain Intent-Classifier-only and must never
    | generate raw SQL from user input.
    |
    */

    'provider' => env('LLM_PROVIDER', 'gemini'),

    'api_key' => env('LLM_API_KEY'),

    'model' => env('LLM_MODEL', 'gemini-2.0-flash'),

    /*
    |--------------------------------------------------------------------------
    | Base URL for OpenAI-compatible providers.
    |--------------------------------------------------------------------------
    |
    | Examples:
    |
    | xAI / Grok:
    | LLM_PROVIDER=openai_compatible
    | LLM_BASE_URL=https://api.x.ai/v1
    |
    | OpenRouter:
    | LLM_PROVIDER=openai_compatible
    | LLM_BASE_URL=https://openrouter.ai/api/v1
    |
    | Local gateway:
    | LLM_PROVIDER=openai_compatible
    | LLM_BASE_URL=http://127.0.0.1:11434/v1
    |
    */
    'base_url' => env('LLM_BASE_URL'),

    'timeout' => env('LLM_TIMEOUT', 30),

    'temperature' => env('LLM_TEMPERATURE', 0.2),

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
