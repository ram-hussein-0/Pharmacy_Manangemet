<?php

namespace Tests\Feature\Ai;

use App\Models\User;
use App\Services\Ai\AiAnalyticalAnswerer;
use App\Services\Ai\AiAnalyticalAssistantService;
use App\Services\Ai\AiAnalyticalExecutor;
use App\Services\Ai\AiAnalyticalPlanner;
use App\Services\Ai\AiAnalyticalPlanValidator;
use App\Services\Ai\AiBusinessSemantics;
use App\Services\Ai\AiDatabaseAssistantService;
use App\Services\Ai\AiSchemaCatalog;
use App\Services\Ai\LlmClientService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class AiLatencyResilienceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_non_thinking_deepseek_call_explicitly_disables_thinking_and_uses_temperature_point_two(): void
    {
        Http::fake([
            'https://api.deepseek.com/chat/completions' => Http::response([
                'choices' => [[
                    'message' => ['content' => '{"answerable":false}'],
                ]],
            ]),
        ]);

        $llm = new LlmClientService(
            apiKey: 'latency-test-key',
            model: 'deepseek-v4-flash',
            provider: 'openai_compatible',
            baseUrl: 'https://api.deepseek.com',
        );

        $result = $llm->completeAdvanced(
            systemPrompt: 'Return JSON only.',
            userPrompt: 'Return a JSON object.',
            jsonMode: true,
            options: [
                'thinking' => false,
                'temperature' => 0.2,
                'timeout' => 5,
            ],
        );

        $this->assertSame('{"answerable":false}', $result);

        Http::assertSent(function ($request): bool {
            $data = $request->data();

            return data_get($data, 'thinking.type') === 'disabled'
                && ($data['temperature'] ?? null) === 0.2
                && ! array_key_exists('reasoning_effort', $data)
                && data_get($data, 'response_format.type') === 'json_object';
        });
    }

    public function test_planner_fast_path_is_non_thinking_and_validator_remains_authoritative(): void
    {
        config([
            'llm.planner_thinking' => false,
            'llm.temperature' => 0.2,
            'llm.planner_timeout' => 10,
        ]);

        $fakeLlm = new class extends LlmClientService {
            public array $calls = [];

            public function __construct()
            {
            }

            public function completeAdvanced(string $systemPrompt, string $userPrompt, bool $jsonMode = false, array $options = []): string
            {
                $this->calls[] = $options;

                return json_encode([
                    'answerable' => true,
                    'reason' => '',
                    'queries' => [[
                        'id' => 'product_count',
                        'from' => 'products',
                        'joins' => [],
                        'select' => [[
                            'kind' => 'aggregate',
                            'function' => 'count',
                            'expression' => ['type' => 'column', 'column' => 'products.id'],
                            'alias' => 'product_count',
                        ]],
                        'filters' => [],
                        'group_by' => [],
                        'order_by' => [],
                        'limit' => 1,
                    ]],
                    'calculations' => [],
                    'display_query' => 'product_count',
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
        };

        $catalog = app(AiSchemaCatalog::class);
        $semantics = app(AiBusinessSemantics::class);
        $validator = new AiAnalyticalPlanValidator($catalog, $semantics);
        $planner = new AiAnalyticalPlanner($fakeLlm, $catalog, $semantics, $validator);

        $plan = $planner->plan('How many products are there?');

        $this->assertTrue($plan['answerable']);
        $this->assertFalse($fakeLlm->calls[0]['thinking']);
        $this->assertSame(0.2, $fakeLlm->calls[0]['temperature']);
        $this->assertSame(10, $fakeLlm->calls[0]['timeout']);
    }

    public function test_final_answer_stream_is_non_thinking_and_uses_configured_temperature(): void
    {
        config([
            'llm.answer_thinking' => false,
            'llm.temperature' => 0.2,
            'llm.answer_timeout' => 60,
        ]);

        $fakeLlm = new class extends LlmClientService {
            public array $options = [];

            public function __construct()
            {
            }

            public function streamAdvanced(string $systemPrompt, string $userPrompt, callable $onChunk, array $options = []): string
            {
                $this->options = $options;
                $onChunk('**Fast** answer');

                return '**Fast** answer';
            }
        };

        $answerer = new AiAnalyticalAnswerer($fakeLlm);
        $streamed = '';

        $answer = $answerer->stream(
            'question',
            [],
            ['queries' => [], 'calculations' => []],
            function (string $chunk) use (&$streamed): void {
                $streamed .= $chunk;
            },
        );

        $this->assertSame('**Fast** answer', $answer);
        $this->assertSame('**Fast** answer', $streamed);
        $this->assertFalse($fakeLlm->options['thinking']);
        $this->assertSame(0.2, $fakeLlm->options['temperature']);
        $this->assertSame(60, $fakeLlm->options['timeout']);
    }

    public function test_provider_timeout_returns_controlled_arabic_response_without_entering_slow_legacy_llm_path(): void
    {
        $admin = User::create([
            'name' => 'Latency Admin '.Str::uuid(),
            'email' => Str::uuid().'@example.com',
            'password' => Hash::make('Password123!'),
            'phone' => '0000000000',
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
        ]);

        Auth::login($admin);
        config(['llm.api_key' => 'configured-test-key']);

        $planner = new class extends AiAnalyticalPlanner {
            public function __construct()
            {
            }

            public function plan(string $question): array
            {
                throw new RuntimeException('cURL error 28: Operation timed out');
            }
        };

        $executor = new AiAnalyticalExecutor();

        $answerer = new class extends AiAnalyticalAnswerer {
            public function __construct()
            {
            }
        };

        $legacy = new class extends AiDatabaseAssistantService {
            public function __construct()
            {
            }

            public function ask(string $question): array
            {
                throw new RuntimeException('Legacy LLM path must not run after provider timeout.');
            }
        };

        $service = new AiAnalyticalAssistantService($planner, $executor, $answerer, $legacy);
        $streamed = '';

        $result = $service->stream('ما المنتجات التي لدي؟', function (string $chunk) use (&$streamed): void {
            $streamed .= $chunk;
        });

        $this->assertSame('analytical_agent_unavailable', $result['intent']);
        $this->assertStringContainsString('المهلة الآمنة', $result['answer']);
        $this->assertSame($result['answer'], $streamed);
        $this->assertSame([], $result['rows']);
    }

    public function test_runtime_defaults_keep_php_window_above_network_budgets(): void
    {
        $this->assertSame(0.2, (float) config('llm.temperature'));
        $this->assertFalse((bool) config('llm.planner_thinking'));
        $this->assertFalse((bool) config('llm.answer_thinking'));
        $this->assertGreaterThan(
            (int) config('llm.answer_timeout'),
            (int) config('llm.request_execution_limit'),
        );
    }
}
