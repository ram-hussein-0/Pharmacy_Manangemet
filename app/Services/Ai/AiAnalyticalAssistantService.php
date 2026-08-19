<?php

namespace App\Services\Ai;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

class AiAnalyticalAssistantService
{
    public function __construct(
        private readonly AiAnalyticalPlanner $planner,
        private readonly AiAnalyticalExecutor $executor,
        private readonly AiAnalyticalAnswerer $answerer,
        private readonly AiDatabaseAssistantService $legacy,
    ) {
    }

    /** @return array{intent:string,answer:string,rows:array,columns:array} */
    public function ask(string $question): array
    {
        $this->extendExecutionWindow();

        $question = trim($question);

        if ($question === '') {
            return $this->fail('Please enter a question.');
        }

        if (mb_strlen($question) > 500) {
            return $this->fail('Question is too long. Please keep it under 500 characters.');
        }

        $user = Auth::user();

        if (! $user || ! $user->is_active || ! $user->isAdmin()) {
            return $this->fail('The analytical assistant is available only to an active administrator.');
        }

        $key = 'ai-analytical:'.$user->getAuthIdentifier();

        if (RateLimiter::tooManyAttempts($key, 30)) {
            return $this->fail('You are sending questions too fast. Please wait a few seconds.');
        }

        RateLimiter::hit($key, 60);
        $started = microtime(true);

        if (blank(config('llm.api_key'))) {
            return $this->legacyFallback($question, 'The analytical LLM is not configured, so a limited verified fallback was used.');
        }

        $rows = [];
        $columns = [];

        try {
            $plan = $this->planner->plan($question);

            if (($plan['answerable'] ?? false) !== true) {
                return $this->fail((string) ($plan['reason'] ?? 'The current database does not provide enough evidence to answer safely.'));
            }

            $evidence = $this->executor->execute($plan);
            [$rows, $columns] = $this->presentationRows($plan, $evidence);
            $answer = $this->answerer->answer($question, $plan, $evidence);

            $this->logSuccess($question, $plan, $rows, $started, 'analytical_agent');

            return [
                'intent' => 'analytical_agent',
                'answer' => $answer,
                'rows' => $rows,
                'columns' => $columns,
            ];
        } catch (Throwable $exception) {
            if ($this->isTransientProviderFailure($exception)) {
                $this->logProviderFailure($exception, $started, false);

                return $this->providerUnavailable($question, $rows, $columns);
            }

            return $this->handleFailure($question, $exception, $started);
        }
    }

    /** @return array{intent:string,answer:string,rows:array,columns:array} */
    public function stream(string $question, callable $onChunk): array
    {
        $this->extendExecutionWindow();

        $question = trim($question);

        if ($question === '') {
            return $this->fail('Please enter a question.');
        }

        if (mb_strlen($question) > 500) {
            return $this->fail('Question is too long. Please keep it under 500 characters.');
        }

        $user = Auth::user();

        if (! $user || ! $user->is_active || ! $user->isAdmin()) {
            return $this->fail('The analytical assistant is available only to an active administrator.');
        }

        $key = 'ai-analytical:'.$user->getAuthIdentifier();

        if (RateLimiter::tooManyAttempts($key, 30)) {
            return $this->fail('You are sending questions too fast. Please wait a few seconds.');
        }

        RateLimiter::hit($key, 60);
        $started = microtime(true);

        if (blank(config('llm.api_key'))) {
            return $this->legacyFallback($question, 'The analytical LLM is not configured, so a limited verified fallback was used.');
        }

        $streamedAnswer = '';
        $rows = [];
        $columns = [];

        try {
            $plan = $this->planner->plan($question);

            if (($plan['answerable'] ?? false) !== true) {
                return $this->fail((string) ($plan['reason'] ?? 'The current database does not provide enough evidence to answer safely.'));
            }

            $evidence = $this->executor->execute($plan);
            [$rows, $columns] = $this->presentationRows($plan, $evidence);

            $answer = $this->answerer->stream(
                $question,
                $plan,
                $evidence,
                function (string $chunk) use (&$streamedAnswer, $onChunk): void {
                    $streamedAnswer .= $chunk;
                    $onChunk($chunk);
                },
            );

            $this->logSuccess($question, $plan, $rows, $started, 'analytical_agent_stream');

            return [
                'intent' => 'analytical_agent',
                'answer' => $answer,
                'rows' => $rows,
                'columns' => $columns,
            ];
        } catch (Throwable $exception) {
            report($exception);

            $this->logProviderFailure($exception, $started, $streamedAnswer !== '');

            if ($streamedAnswer !== '') {
                return [
                    'intent' => 'analytical_agent',
                    'answer' => trim($streamedAnswer)."\n\n_Response interrupted before completion. Please retry if you need the full answer._",
                    'rows' => $rows,
                    'columns' => $columns,
                ];
            }

            if ($this->isTransientProviderFailure($exception)) {
                $fallback = $this->providerUnavailable($question, $rows, $columns);
                $onChunk($fallback['answer']);

                return $fallback;
            }

            return $this->legacyFallback(
                $question,
                'I could not complete a safe verified analytical plan, so I tried the limited verified fallback.',
            );
        }
    }

    /** @return array{0:array,1:array} */
    private function presentationRows(array $plan, array $evidence): array
    {
        $displayQuery = $plan['display_query'] ?? null;
        $rows = is_string($displayQuery) ? ($evidence['queries'][$displayQuery] ?? []) : [];

        if ($rows === [] && ($evidence['calculations'] ?? []) !== []) {
            $rows = [$evidence['calculations']];
        }

        $columns = $rows !== [] && is_array($rows[0]) ? array_keys($rows[0]) : [];

        return [$rows, $columns];
    }

    private function extendExecutionWindow(): void
    {
        $seconds = max(60, min((int) config('llm.request_execution_limit', 120), 300));

        if (function_exists('set_time_limit')) {
            @set_time_limit($seconds);
        }
    }

    private function isTransientProviderFailure(Throwable $exception): bool
    {
        $current = $exception;

        while ($current) {
            if ($current instanceof ConnectionException) {
                return true;
            }

            $message = strtolower($current->getMessage());

            if (
                str_contains($message, 'timed out')
                || str_contains($message, 'timeout')
                || str_contains($message, 'curl error 28')
                || str_contains($message, 'rate limit')
                || preg_match('/llm error (408|425|429|500|502|503|504)\b/', $message) === 1
            ) {
                return true;
            }

            $current = $current->getPrevious();
        }

        return false;
    }

    /** @return array{intent:string,answer:string,rows:array,columns:array} */
    private function providerUnavailable(string $question, array $rows = [], array $columns = []): array
    {
        $arabic = preg_match('/\p{Arabic}/u', $question) === 1;
        $hasVerifiedRows = $rows !== [];

        if ($arabic) {
            $answer = $hasVerifiedRows
                ? 'تم تنفيذ الاستعلام والتحقق من البيانات، لكن خدمة صياغة الإجابة تأخرت عن المهلة الآمنة. **النتائج الموثقة معروضة أدناه** ويمكنك إعادة المحاولة للحصول على الشرح النصي الكامل.'
                : 'خدمة الذكاء الاصطناعي تأخرت أو لم تستجب ضمن المهلة الآمنة. لم يتم تخمين أي نتيجة أو تنفيذ استعلام غير موثّق. **حاول مرة أخرى بعد لحظات.**';
        } else {
            $answer = $hasVerifiedRows
                ? 'The query and verification completed, but the AI wording service exceeded the safe time budget. **The verified results are shown below.** Retry if you want the full narrative explanation.'
                : 'The AI provider did not respond within the safe time budget. No result was guessed and no unverified query was executed. **Please try again in a moment.**';
        }

        return [
            'intent' => 'analytical_agent_unavailable',
            'answer' => $answer,
            'rows' => $rows,
            'columns' => $columns,
        ];
    }

    private function logSuccess(string $question, array $plan, array $rows, float $started, string $mode): void
    {
        Log::info('ai.analytical_assistant', [
            'user_id' => Auth::id(),
            'mode' => $mode,
            'queries' => count($plan['queries'] ?? []),
            'rows' => count($rows),
            'latency_ms' => (int) ((microtime(true) - $started) * 1000),
            'question' => config('llm.log_questions') ? $question : null,
        ]);
    }

    private function logProviderFailure(Throwable $exception, float $started, bool $streamStarted): void
    {
        Log::warning('ai.analytical_assistant_provider_failure', [
            'user_id' => Auth::id(),
            'exception' => $exception::class,
            'stream_started' => $streamStarted,
            'latency_ms' => (int) ((microtime(true) - $started) * 1000),
        ]);
    }

    private function handleFailure(string $question, Throwable $exception, float $started): array
    {
        report($exception);

        Log::warning('ai.analytical_assistant_fallback', [
            'user_id' => Auth::id(),
            'exception' => $exception::class,
            'latency_ms' => (int) ((microtime(true) - $started) * 1000),
        ]);

        return $this->legacyFallback(
            $question,
            'I could not complete a safe verified analytical plan, so I tried the limited verified fallback.',
        );
    }

    private function legacyFallback(string $question, string $notice): array
    {
        $legacy = $this->legacy->ask($question);

        if (($legacy['intent'] ?? 'unknown') === 'unknown') {
            return $this->fail($notice.' The fallback also did not have enough verified coverage for this question.');
        }

        $legacy['answer'] = $notice."\n\n".$legacy['answer'];

        return $legacy;
    }

    /** @return array{intent:string,answer:string,rows:array,columns:array} */
    private function fail(string $message): array
    {
        return [
            'intent' => 'unknown',
            'answer' => $message,
            'rows' => [],
            'columns' => [],
        ];
    }
}
