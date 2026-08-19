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

    private int $connectTimeout;

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
        $this->timeout = max(1, (int) config('llm.timeout', 12));
        $this->connectTimeout = max(1, (int) config('llm.connect_timeout', 3));
        $this->temperature = $this->clampTemperature((float) config('llm.temperature', 0.2));
    }

    public function complete(string $systemPrompt, string $userPrompt, bool $jsonMode = false): string
    {
        return $this->completeAdvanced($systemPrompt, $userPrompt, $jsonMode);
    }

    public function completeAdvanced(string $systemPrompt, string $userPrompt, bool $jsonMode = false, array $options = []): string
    {
        if (blank($this->apiKey)) {
            throw new LlmNotConfiguredException();
        }

        return match ($this->provider) {
            'gemini' => $this->completeWithGemini($systemPrompt, $userPrompt, $jsonMode, $options),
            'openai_compatible' => $this->completeWithOpenAiCompatible($systemPrompt, $userPrompt, $jsonMode, $options),
            default => throw new RuntimeException("Unsupported LLM provider [{$this->provider}]. Use gemini or openai_compatible."),
        };
    }

    public function streamAdvanced(string $systemPrompt, string $userPrompt, callable $onChunk, array $options = []): string
    {
        if (blank($this->apiKey)) {
            throw new LlmNotConfiguredException();
        }

        if ($this->provider !== 'openai_compatible') {
            $answer = $this->completeAdvanced($systemPrompt, $userPrompt, false, $options);

            if ($answer !== '') {
                $onChunk($answer);
            }

            return $answer;
        }

        return $this->streamWithOpenAiCompatible($systemPrompt, $userPrompt, $onChunk, $options);
    }

    private function completeWithGemini(string $systemPrompt, string $userPrompt, bool $jsonMode, array $options): string
    {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key={$this->apiKey}";

        $generationConfig = [
            'temperature' => $this->temperatureFor($options),
            'response_mime_type' => $jsonMode ? 'application/json' : null,
        ];

        if (isset($options['max_tokens'])) {
            $generationConfig['maxOutputTokens'] = max(1, (int) $options['max_tokens']);
        }

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
            'generationConfig' => array_filter($generationConfig, fn ($value) => $value !== null),
        ];

        $response = Http::connectTimeout($this->connectTimeoutFor($options))
            ->timeout($this->timeoutFor($options, $this->timeout))
            ->post($url, $body);

        $this->throwIfFailed($response->status(), $response->body());

        return (string) data_get($response->json(), 'candidates.0.content.parts.0.text', '');
    }

    private function completeWithOpenAiCompatible(string $systemPrompt, string $userPrompt, bool $jsonMode, array $options): string
    {
        $baseUrl = $this->openAiBaseUrl();
        $body = $this->openAiBody($systemPrompt, $userPrompt, $jsonMode, $options);

        $response = Http::connectTimeout($this->connectTimeoutFor($options))
            ->timeout($this->timeoutFor($options, $this->timeout))
            ->withToken($this->apiKey)
            ->acceptJson()
            ->post($baseUrl.'/chat/completions', $body);

        $this->throwIfFailed($response->status(), $response->body());

        return (string) data_get($response->json(), 'choices.0.message.content', '');
    }

    private function streamWithOpenAiCompatible(string $systemPrompt, string $userPrompt, callable $onChunk, array $options): string
    {
        $baseUrl = $this->openAiBaseUrl();
        $body = $this->openAiBody($systemPrompt, $userPrompt, false, $options);
        $body['stream'] = true;

        $defaultStreamTimeout = max(10, (int) config('llm.stream_timeout', 60));

        $response = Http::connectTimeout($this->connectTimeoutFor($options))
            ->timeout($this->timeoutFor($options, $defaultStreamTimeout))
            ->withOptions(['stream' => true])
            ->withToken($this->apiKey)
            ->accept('text/event-stream')
            ->post($baseUrl.'/chat/completions', $body);

        $status = $response->status();

        if ($status < 200 || $status >= 300) {
            $this->throwIfFailed($status, $response->body());
        }

        $stream = $response->toPsrResponse()->getBody();
        $buffer = '';
        $answer = '';

        try {
            while (! $stream->eof()) {
                $bytes = $stream->read(4096);

                if ($bytes === '') {
                    usleep(1000);
                    continue;
                }

                $buffer .= str_replace("\r", '', $bytes);

                while (($separator = strpos($buffer, "\n\n")) !== false) {
                    $event = substr($buffer, 0, $separator);
                    $buffer = substr($buffer, $separator + 2);
                    $this->consumeSseEvent($event, $answer, $onChunk);
                }
            }

            if (trim($buffer) !== '') {
                $this->consumeSseEvent($buffer, $answer, $onChunk);
            }
        } finally {
            $response->close();
        }

        return $answer;
    }

    private function consumeSseEvent(string $event, string &$answer, callable $onChunk): void
    {
        foreach (explode("\n", $event) as $line) {
            if (! str_starts_with($line, 'data:')) {
                continue;
            }

            $data = trim(substr($line, 5));

            if ($data === '' || $data === '[DONE]') {
                continue;
            }

            $decoded = json_decode($data, true);

            if (! is_array($decoded)) {
                throw new RuntimeException('Malformed streamed LLM event.');
            }

            // Only final content is exposed. reasoning_content is deliberately
            // ignored even if a bounded thinking repair ever uses streaming.
            $content = data_get($decoded, 'choices.0.delta.content');

            if (! is_string($content) || $content === '') {
                continue;
            }

            $answer .= $content;
            $onChunk($content);
        }
    }

    private function openAiBaseUrl(): string
    {
        $baseUrl = rtrim((string) $this->baseUrl, '/');

        if ($baseUrl === '') {
            throw new RuntimeException('LLM_BASE_URL is required when LLM_PROVIDER=openai_compatible.');
        }

        return $baseUrl;
    }

    private function openAiBody(string $systemPrompt, string $userPrompt, bool $jsonMode, array $options): array
    {
        $baseUrl = $this->openAiBaseUrl();
        $isDeepSeek = str_contains(strtolower($baseUrl), 'api.deepseek.com');
        $thinking = (bool) ($options['thinking'] ?? $isDeepSeek);

        $body = [
            'model' => $this->model,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
            ],
        ];

        if ($isDeepSeek) {
            // DeepSeek V4 defaults to thinking=enabled. Explicitly disable it
            // for low-latency calls so temperature actually takes effect.
            $body['thinking'] = ['type' => $thinking ? 'enabled' : 'disabled'];

            if ($thinking) {
                $body['reasoning_effort'] = (string) ($options['reasoning_effort'] ?? config('llm.reasoning_effort', 'high'));
            } else {
                $body['temperature'] = $this->temperatureFor($options);
            }
        } else {
            $body['temperature'] = $this->temperatureFor($options);
        }

        if (isset($options['max_tokens'])) {
            $body['max_tokens'] = max(1, (int) $options['max_tokens']);
        }

        if ($jsonMode) {
            $body['response_format'] = ['type' => 'json_object'];
        }

        return $body;
    }

    private function temperatureFor(array $options): float
    {
        return $this->clampTemperature((float) ($options['temperature'] ?? $this->temperature));
    }

    private function clampTemperature(float $temperature): float
    {
        return max(0.0, min($temperature, 2.0));
    }

    private function timeoutFor(array $options, int $fallback): int
    {
        return max(1, min((int) ($options['timeout'] ?? $fallback), 120));
    }

    private function connectTimeoutFor(array $options): int
    {
        return max(1, min((int) ($options['connect_timeout'] ?? $this->connectTimeout), 15));
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
