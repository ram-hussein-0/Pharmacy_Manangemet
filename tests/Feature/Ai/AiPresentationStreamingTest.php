<?php

namespace Tests\Feature\Ai;

use App\Models\User;
use App\Services\Ai\AiMarkdownRenderer;
use App\Services\Ai\LlmClientService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class AiPresentationStreamingTest extends TestCase
{
    use DatabaseTransactions;

    public function test_markdown_renderer_formats_markdown_safely_and_assigns_direction_per_block(): void
    {
        $renderer = app(AiMarkdownRenderer::class);
        $html = $renderer->render("بناءً على البيانات، **ogmantin** متوفر.\n\n1. **ogmantin** — الحالة: نشط\n2. **paracetamol** — المخزون متوفر\n\nEnglish **summary** is available.\n\n<script>alert('x')</script>");

        $this->assertStringContainsString('<strong>ogmantin</strong>', $html);
        $this->assertMatchesRegularExpression('/<p[^>]*dir="rtl"[^>]*>.*ogmantin/s', $html);
        $this->assertMatchesRegularExpression('/<ol[^>]*dir="rtl"/', $html);
        $this->assertMatchesRegularExpression('/<p[^>]*dir="ltr"[^>]*>English/s', $html);
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('javascript:', strtolower($renderer->render('[bad](javascript:alert(1))')));
    }

    public function test_deepseek_streaming_emits_only_final_content_and_never_reasoning_content(): void
    {
        $sse = implode("\n\n", [
            'data: '.json_encode(['choices' => [['delta' => ['reasoning_content' => 'hidden chain of thought', 'content' => '']]]]),
            'data: '.json_encode(['choices' => [['delta' => ['content' => 'مرحبا ']]]]),
            'data: '.json_encode(['choices' => [['delta' => ['content' => '**world**']]]]),
            'data: [DONE]',
        ])."\n\n";

        Http::fake([
            'https://api.deepseek.com/chat/completions' => Http::response($sse, 200, ['Content-Type' => 'text/event-stream']),
        ]);

        $llm = new LlmClientService(
            apiKey: 'stream-test-key',
            model: 'deepseek-v4-flash',
            provider: 'openai_compatible',
            baseUrl: 'https://api.deepseek.com',
        );

        $chunks = [];
        $answer = $llm->streamAdvanced(
            systemPrompt: 'Answer only.',
            userPrompt: 'Hello',
            onChunk: function (string $chunk) use (&$chunks): void {
                $chunks[] = $chunk;
            },
            options: [
                'thinking' => true,
                'reasoning_effort' => 'high',
                'max_tokens' => 256,
            ],
        );

        $this->assertSame('مرحبا **world**', $answer);
        $this->assertSame(['مرحبا ', '**world**'], $chunks);
        $this->assertStringNotContainsString('hidden chain of thought', implode('', $chunks));

        Http::assertSent(function ($request): bool {
            $data = $request->data();

            return $request->url() === 'https://api.deepseek.com/chat/completions'
                && ($data['model'] ?? null) === 'deepseek-v4-flash'
                && ($data['stream'] ?? null) === true
                && data_get($data, 'thinking.type') === 'enabled'
                && ($data['reasoning_effort'] ?? null) === 'high'
                && ! array_key_exists('temperature', $data);
        });
    }

    public function test_ai_page_contains_livewire_stream_target_and_production_markdown_container(): void
    {
        $this->actingAs($this->makeAdmin());

        $this->get('/admin/ai-database-assistant')
            ->assertOk()
            ->assertSee('wire:stream="ai-answer-stream"', false)
            ->assertSee('ai-markdown', false)
            ->assertSee('Analyzing verified data…');
    }

    private function makeAdmin(): User
    {
        return User::create([
            'name' => 'Streaming Admin '.Str::uuid(),
            'email' => Str::uuid().'@example.com',
            'password' => Hash::make('Password123!'),
            'phone' => '0000000000',
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
        ]);
    }
}
