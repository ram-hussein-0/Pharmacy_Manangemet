<?php

namespace App\Filament\Pages;

use App\Services\Ai\AiDatabaseAssistantService;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class AiDatabaseAssistant extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-sparkles';

    protected static string|\UnitEnum|null $navigationGroup = 'Administration';

    protected static ?string $navigationLabel = 'AI Database Assistant';

    protected static ?int $navigationSort = 90;

    protected static ?string $title = 'AI Database Assistant';

    protected string $view = 'filament.pages.ai-database-assistant';

    public string $question = '';

    /**
     * @var array<int, array{role:string, content:string, intent?:string, rows?:array, columns?:array}>
     */
    public array $messages = [];

    public static function canAccess(): bool
    {
        return Auth::check() && Auth::user()?->isAdmin();
    }

    public function mount(): void
    {
        $this->messages = [[
            'role' => 'assistant',
            'content' => 'Hi! Ask me about stock, expiry, sales, profit, suppliers, products, or stock movements.',
        ]];
    }

    public function send(AiDatabaseAssistantService $service): void
    {
        $question = trim($this->question);

        if ($question === '') {
            return;
        }

        if (mb_strlen($question) > 500) {
            $this->messages[] = [
                'role' => 'assistant',
                'content' => 'Please keep your question under 500 characters.',
                'intent' => 'unknown',
                'rows' => [],
                'columns' => [],
            ];

            return;
        }

        $this->messages[] = [
            'role' => 'user',
            'content' => $question,
        ];

        $this->question = '';

        $result = $service->ask($question);

        $this->messages[] = [
            'role' => 'assistant',
            'content' => $result['answer'],
            'intent' => $result['intent'],
            'rows' => $result['rows'],
            'columns' => $result['columns'],
        ];
    }
}
