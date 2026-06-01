<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class LlmClientService
{
    private ?string $apiKey;

    private string $model;

    private string $provider;

    private ?string $baseUrl;

    private int $timeout;

    private float $temperature;

    public function __construct(
        ?string $apiKey = null,
        ?string $model = null,
        ?string $provider = null,
        ?string $baseUrl = null,
    ) {
        $this->provider = $provider ?? (string) config('llm.provider', 'gemini');
        $this->apiKey = $apiKey ?? config('llm.api_key');
        $this->model = $model ?? (string) config('llm.model', 'gemini-2.0-flash');
        $this->baseUrl = $baseUrl ?? config('llm.base_url');
        $this->timeout = (int) config('llm.timeout', 30);
        $this->temperature = (float) config('llm.temperature', 0.2);
    }

    public function complete(string $systemPrompt, string $userPrompt, bool $jsonMode = false): string
    {
        if (blank($this->apiKey)) {
            throw new LlmNotConfiguredException();
        }

        return match ($this->provider) {
            'gemini' => $this->completeWithGemini($systemPrompt, $userPrompt, $jsonMode),
            'openai_compatible' => $this->completeWithOpenAiCompatible($systemPrompt, $userPrompt, $jsonMode),
            default => throw new RuntimeException("Unsupported LLM provider [{$this->provider}]. Use gemini or openai_compatible."),
        };
    }

    private function completeWithGemini(string $systemPrompt, string $userPrompt, bool $jsonMode): string
    {
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
                'temperature' => $this->temperature,
                'response_mime_type' => $jsonMode ? 'application/json' : null,
            ]),
        ];

        $response = Http::timeout($this->timeout)->post($url, $body);

        $this->throwIfFailed($response->status(), $response->body());

        return (string) data_get($response->json(), 'candidates.0.content.parts.0.text', '');
    }

    private function completeWithOpenAiCompatible(string $systemPrompt, string $userPrompt, bool $jsonMode): string
    {
        $baseUrl = rtrim((string) $this->baseUrl, '/');

        if ($baseUrl === '') {
            throw new RuntimeException('LLM_BASE_URL is required when LLM_PROVIDER=openai_compatible.');
        }

        $body = [
            'model' => $this->model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $systemPrompt,
                ],
                [
                    'role' => 'user',
                    'content' => $userPrompt,
                ],
            ],
            'temperature' => $this->temperature,
        ];

        if ($jsonMode) {
            $body['response_format'] = [
                'type' => 'json_object',
            ];
        }

        $response = Http::timeout($this->timeout)
            ->withToken($this->apiKey)
            ->acceptJson()
            ->post($baseUrl.'/chat/completions', $body);

        $this->throwIfFailed($response->status(), $response->body());

        return (string) data_get($response->json(), 'choices.0.message.content', '');
    }

    private function throwIfFailed(int $status, string $body): void
    {
        if ($status === 429) {
            throw new RuntimeException('LLM rate limit exceeded. Please wait and try again.');
        }

        if ($status < 200 || $status >= 300) {
            throw new RuntimeException('LLM error '.$status.': '.$body);
        }
    }
}
