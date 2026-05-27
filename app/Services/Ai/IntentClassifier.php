<?php

namespace App\Services\Ai;

use Throwable;

class IntentClassifier
{
    public function __construct(private readonly LlmClientService $llm)
    {
    }

    /**
     * @return array{intent:string, params:array}
     */
    public function classify(string $question): array
    {
        $allowed = config('llm.allowed_intents', ['unknown']);
        $list = collect($allowed)->map(fn (string $intent): string => "- {$intent}")->implode("\n");

        $prompt = <<<PROMPT
The user asked in Arabic or English:
"{$question}"

Classify into exactly one of these allowed intents:
{$list}

Optional params:
- product_name (string) for product_lookup
- days (int, default 30) for expiring_batches
- date_from and date_to (YYYY-MM-DD) for sales_between_dates
- limit (int, default 5) for top_selling_products

Return strict JSON only:
{"intent":"...","params":{}}

If the question is unrelated to pharmacy data, return:
{"intent":"unknown","params":{}}
PROMPT;

        try {
            $raw = $this->llm->complete(
                systemPrompt: 'You output only valid minified JSON. No prose. No markdown. No SQL.',
                userPrompt: $prompt,
                jsonMode: true,
            );

            $parsed = json_decode($raw, true);

            if (! is_array($parsed)) {
                return ['intent' => 'unknown', 'params' => []];
            }

            $intent = $parsed['intent'] ?? 'unknown';

            if (! in_array($intent, $allowed, true)) {
                $intent = 'unknown';
            }

            $params = $parsed['params'] ?? [];

            return [
                'intent' => $intent,
                'params' => is_array($params) ? $params : [],
            ];
        } catch (Throwable $exception) {
            report($exception);

            return ['intent' => 'unknown', 'params' => []];
        }
    }
}
