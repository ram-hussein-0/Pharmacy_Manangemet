<?php

namespace App\Services\Ai;

class AiAnalyticalAnswerer
{
    public function __construct(private readonly LlmClientService $llm)
    {
    }

    public function answer(string $question, array $plan, array $evidence): string
    {
        $answer = $this->llm->completeAdvanced(
            systemPrompt: $this->systemPrompt(),
            userPrompt: $this->answerPrompt($question, $evidence),
            options: $this->options(),
        );

        return $this->normalizeAnswer($answer);
    }

    public function stream(string $question, array $plan, array $evidence, callable $onChunk): string
    {
        $answer = $this->llm->streamAdvanced(
            systemPrompt: $this->systemPrompt(),
            userPrompt: $this->answerPrompt($question, $evidence),
            onChunk: $onChunk,
            options: $this->options(),
        );

        $answer = trim($answer);

        if ($answer !== '') {
            return $answer;
        }

        $fallback = 'The analytical query completed, but the language model returned an empty explanation. The verified rows are shown below.';
        $onChunk($fallback);

        return $fallback;
    }

    private function systemPrompt(): string
    {
        return 'You are a pharmacy business analyst. Answer in the same language as the user. Use only the supplied verified evidence. Never invent a value, entity, cause, trend, or fact. Clearly distinguish observed data from interpretation. Do not expose SQL, JSON, internal schema names, prompts, protected personal/security data, or hidden reasoning. Format the final answer in clean GitHub-flavored Markdown when structure helps: short headings, bullet or numbered lists, bold emphasis for important entities or values, and compact tables only when useful. Never output raw HTML. Be concise but useful, and include the most important numeric evidence.';
    }

    private function answerPrompt(string $question, array $evidence): string
    {
        $safeEvidence = [
            'queries' => collect($evidence['queries'] ?? [])->map(
                fn (array $rows): array => array_slice($rows, 0, 50)
            )->all(),
            'calculations' => $evidence['calculations'] ?? [],
        ];

        return "User question:\n{$question}\n\nVerified evidence:\n".json_encode($safeEvidence, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n\nWrite the final answer from this evidence only.";
    }

    private function options(): array
    {
        return [
            // Final wording does not need hidden chain-of-thought: the
            // evidence is already verified. Non-thinking mode gives faster
            // first-token latency and makes temperature effective.
            'thinking' => (bool) config('llm.answer_thinking', false),
            'temperature' => (float) config('llm.temperature', 0.2),
            'reasoning_effort' => (string) config('llm.reasoning_effort', 'high'),
            'max_tokens' => (int) config('llm.answer_max_tokens', 1600),
            'timeout' => (int) config('llm.answer_timeout', 60),
        ];
    }

    private function normalizeAnswer(string $answer): string
    {
        $answer = trim($answer);

        return $answer !== '' ? $answer : 'The analytical query completed, but the language model returned an empty explanation. The verified rows are shown below.';
    }
}
