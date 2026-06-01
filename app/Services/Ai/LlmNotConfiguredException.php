<?php

namespace App\Services\Ai;

use RuntimeException;

class LlmNotConfiguredException extends RuntimeException
{
    public function __construct(?string $message = null)
    {
        parent::__construct($message ?: 'AI Database Assistant is not configured. Set LLM_PROVIDER, LLM_MODEL, and LLM_API_KEY in your local .env file.');
    }
}
