<?php

namespace App\Filament\Pages;

use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Ai\AiAnalyticalAssistantService;
use App\Services\Ai\AiMarkdownRenderer;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class AiDatabaseAssistant extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-sparkles';

    protected static string|\UnitEnum|null $navigationGroup = 'Administration';

    protected static ?string $navigationLabel = 'AI Assistant';

    protected static ?int $navigationSort = 90;

    protected static ?string $title = 'AI Assistant';

    protected string $view = 'filament.pages.ai-database-assistant';

    public string $question = '';

    public string $entityType = 'product';

    public string $entitySearch = '';

    public bool $entityPickerOpen = false;

    /**
     * @var array<int, array{role:string, content:string, intent?:string, rows?:array, columns?:array}>
     */
    public array $messages = [];

    public static function canAccess(): bool
    {
        return Auth::check() && Auth::user()?->is_active && Auth::user()?->isAdmin();
    }

    public function mount(): void
    {
        $this->messages = [[
            'role' => 'assistant',
            'content' => 'Hi! Ask me about your pharmacy or business. I can analyze verified read-only data and explain the result clearly.',
        ]];
    }

    /**
     * @return array<string, string>
     */
    public function entityTypes(): array
    {
        return [
            'product' => 'Products / Medicines',
            'supplier' => 'Suppliers',
            'staff' => 'Staff',
            'category' => 'Categories',
        ];
    }

    /**
     * @return array<int, array{id:int, name:string, meta:string}>
     */
    public function getEntityOptions(): array
    {
        if (! $this->entityPickerOpen) {
            return [];
        }

        $search = trim($this->entitySearch);

        return match ($this->entityType) {
            'supplier' => Supplier::query()
                ->select(['id', 'name', 'phone'])
                ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"))
                ->orderBy('name')
                ->limit(15)
                ->get()
                ->map(fn (Supplier $supplier): array => [
                    'id' => (int) $supplier->id,
                    'name' => $supplier->name,
                    'meta' => $supplier->phone ?: 'Supplier',
                ])
                ->all(),
            'staff' => User::query()
                ->select(['id', 'name', 'role', 'is_active'])
                ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"))
                ->orderBy('name')
                ->limit(15)
                ->get()
                ->map(fn (User $user): array => [
                    'id' => (int) $user->id,
                    'name' => $user->name,
                    'meta' => ucfirst($user->role).' · '.($user->is_active ? 'Active' : 'Inactive'),
                ])
                ->all(),
            'category' => Category::query()
                ->select(['id', 'name'])
                ->withCount('products')
                ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"))
                ->orderBy('name')
                ->limit(15)
                ->get()
                ->map(fn (Category $category): array => [
                    'id' => (int) $category->id,
                    'name' => $category->name,
                    'meta' => number_format((int) $category->products_count).' product(s)',
                ])
                ->all(),
            default => Product::query()
                ->select(['id', 'name', 'barcode', 'is_active'])
                ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('barcode', 'like', "%{$search}%");
                }))
                ->orderBy('name')
                ->limit(15)
                ->get()
                ->map(fn (Product $product): array => [
                    'id' => (int) $product->id,
                    'name' => $product->name,
                    'meta' => $product->barcode.' · '.($product->is_active ? 'Active' : 'Inactive'),
                ])
                ->all(),
        };
    }


    public function toggleEntityPicker(): void
    {
        $this->entityPickerOpen = ! $this->entityPickerOpen;

        if (! $this->entityPickerOpen) {
            $this->entitySearch = '';
        }
    }

    public function closeEntityPicker(): void
    {
        $this->entityPickerOpen = false;
        $this->entitySearch = '';
    }

    public function updatedEntityType(): void
    {
        $this->entitySearch = '';
    }

    public function insertEntity(string $type, int $id): void
    {
        $name = match ($type) {
            'product' => Product::query()->whereKey($id)->value('name'),
            'supplier' => Supplier::query()->whereKey($id)->value('name'),
            'staff' => User::query()->whereKey($id)->value('name'),
            'category' => Category::query()->whereKey($id)->value('name'),
            default => null,
        };

        if (! is_string($name) || trim($name) === '') {
            Notification::make()
                ->title('Entity not found')
                ->body('Refresh the entity list and try again.')
                ->warning()
                ->send();

            return;
        }

        $tag = match ($type) {
            'supplier' => 'supplier',
            'staff' => 'staff',
            'category' => 'category',
            default => 'product',
        };

        $fragment = $tag.': "'.str_replace('"', '\\"', trim($name)).'"';
        $current = trim($this->question);

        $this->question = $current === '' ? $fragment : rtrim($current).' '.$fragment;
        $this->entityPickerOpen = false;
        $this->entitySearch = '';
    }

    public function send(AiAnalyticalAssistantService $service, AiMarkdownRenderer $renderer): void
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
        $streamedMarkdown = '';

        $result = $service->stream($question, function (string $chunk) use (&$streamedMarkdown, $renderer): void {
            $streamedMarkdown .= $chunk;

            // Re-render the accumulated answer as sanitized Markdown for each
            // streamed delta. Livewire replaces only the temporary answer
            // bubble, so formatting improves progressively as Markdown closes.
            $this->stream(
                to: 'ai-answer-stream',
                content: $renderer->render($streamedMarkdown),
                replace: true,
            );
        });

        // Ensure a verified fallback, an interruption notice, or any final
        // normalization is also shown through the stream target before the
        // normal Livewire render replaces the temporary bubble.
        if (trim((string) $result['answer']) !== trim($streamedMarkdown)) {
            $this->stream(
                to: 'ai-answer-stream',
                content: $renderer->render((string) $result['answer']),
                replace: true,
            );
        }

        $this->messages[] = [
            'role' => 'assistant',
            'content' => $result['answer'],
            'intent' => $result['intent'],
            'rows' => $result['rows'],
            'columns' => $result['columns'],
        ];
    }
}
