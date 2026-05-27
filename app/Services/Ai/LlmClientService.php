<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class LlmClientService
{
    private ?string $apiKey;

    private string $model;

    public function __construct(?string $apiKey = null, ?string $model = null)
    {
        $this->apiKey = $apiKey ?? config('llm.api_key');
        $this->model = $model ?? config('llm.model', 'gemini-2.0-flash');
    }

    public function complete(string $systemPrompt, string $userPrompt, bool $jsonMode = false): string
    {
        if (empty($this->apiKey)) {
            throw new LlmNotConfiguredException();
        }

        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key={$this->apiKey}";

        $body = [
            'system_instruction' => [
                'parts' => [
                    ['text' => $systemPrompt],
                ],
            ],
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => $userPrompt],
                    ],
                ],
            ],
            'generationConfig' => array_filter([
                'temperature' => 0.2,
                'response_mime_type' => $jsonMode ? 'application/json' : null,
            ]),
        ];

        $response = Http::timeout(30)->post($url, $body);

        if ($response->status() === 429) {
            throw new RuntimeException('LLM rate limit exceeded. Please wait and try again.');
        }

        if (! $response->successful()) {
            throw new RuntimeException('LLM error '.$response->status().': '.$response->body());
        }

        return (string) data_get($response->json(), 'candidates.0.content.parts.0.text', '');
    }
}
