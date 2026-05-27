<?php

namespace Tests\Feature\Ai;

use App\Models\Category;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Ai\AiDatabaseAssistantService;
use App\Services\Ai\IntentClassifier;
use App\Services\Ai\LlmClientService;
use App\Services\Ai\LlmNotConfiguredException;
use App\Services\InventoryService;
use App\Services\PurchaseInvoiceService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class AiAssistantCoreTest extends TestCase
{
    use DatabaseTransactions;

    public function test_llm_client_requires_api_key(): void
    {
        config([
            'llm.api_key' => null,
            'llm.model' => 'gemini-test',
        ]);

        $this->expectException(LlmNotConfiguredException::class);

        app(LlmClientService::class)->complete(
            systemPrompt: 'System prompt',
            userPrompt: 'User prompt',
        );
    }

    public function test_intent_classifier_returns_unknown_when_llm_is_not_configured(): void
    {
        config([
            'llm.api_key' => null,
            'llm.model' => 'gemini-test',
            'llm.allowed_intents' => [
                'inventory_summary',
                'unknown',
            ],
        ]);

        $result = app(IntentClassifier::class)->classify('What is the inventory summary?');

        $this->assertSame('unknown', $result['intent']);
        $this->assertSame([], $result['params']);
    }

    public function test_ai_database_assistant_runs_fixed_inventory_summary_without_real_llm_api(): void
    {
        $this->actingAsTestAdmin();

        [$supplier, $product] = $this->makeSupplierAndProduct('ai-inventory-summary');

        $stockBefore = (int) $product->productBatches()->sum('quantity');
        $valueBefore = (float) app(InventoryService::class)->totalStockValue();

        $this->createCompletedPurchase(
            product: $product,
            supplier: $supplier,
            quantity: 4,
            unitPrice: 25,
            batchNumber: 'AI-INVENTORY-BATCH-' . Str::uuid(),
            expiryDate: now()->addMonths(6)->toDateString(),
        );

        $fakeClassifier = new class extends IntentClassifier {
            public function __construct()
            {
            }

            public function classify(string $question): array
            {
                return [
                    'intent' => 'inventory_summary',
                    'params' => [],
                ];
            }
        };

        $fakeLlm = new class extends LlmClientService {
            public function __construct()
            {
            }

            public function complete(string $systemPrompt, string $userPrompt, bool $jsonMode = false): string
            {
                return 'Inventory summary generated from fixed rows.';
            }
        };

        $service = new AiDatabaseAssistantService(
            classifier: $fakeClassifier,
            inventory: app(InventoryService::class),
            llm: $fakeLlm,
        );

        $result = $service->ask('Give me inventory summary');

        $this->assertSame('inventory_summary', $result['intent']);
        $this->assertSame('Inventory summary generated from fixed rows.', $result['answer']);
        $this->assertSame(['total_products', 'active_products', 'units_in_stock', 'stock_value'], $result['columns']);
        $this->assertCount(1, $result['rows']);

        $row = $result['rows'][0];

        $this->assertGreaterThanOrEqual(1, $row['total_products']);
        $this->assertGreaterThanOrEqual(1, $row['active_products']);
        $this->assertSame($stockBefore + 4, (int) $row['units_in_stock']);
        $this->assertEquals($valueBefore + 100.00, (float) $row['stock_value']);
    }

    private function actingAsTestAdmin(): User
    {
        $user = User::create([
            'name' => 'AI Test Admin',
            'email' => 'ai-test-admin-' . Str::uuid() . '@example.com',
            'password' => Hash::make('password'),
            'phone' => '0000000000',
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
        ]);

        Auth::login($user);

        return $user;
    }

    /**
     * @return array{0: Supplier, 1: Product}
     */
    private function makeSupplierAndProduct(string $label): array
    {
        $suffix = strtoupper($label) . '-' . Str::uuid();

        $category = Category::create([
            'name' => "AI Category {$suffix}",
            'description' => "Temporary AI category {$suffix}",
        ]);

        $supplier = Supplier::create([
            'name' => "AI Supplier {$suffix}",
            'phone' => '0000000000',
            'email' => strtolower("ai-supplier-{$suffix}@example.com"),
            'address' => 'Temporary AI address',
            'notes' => "Temporary AI supplier {$suffix}",
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'name' => "AI Product {$suffix}",
            'barcode' => "AI-BARCODE-{$suffix}",
            'description' => "Temporary AI product {$suffix}",
            'sale_price' => 100,
            'minimum_stock' => 5,
            'is_active' => true,
        ]);

        return [$supplier, $product];
    }

    private function createCompletedPurchase(
        Product $product,
        Supplier $supplier,
        int $quantity,
        float $unitPrice,
        string $batchNumber,
        string $expiryDate,
    ): PurchaseInvoice {
        return app(PurchaseInvoiceService::class)->create([
            'supplier_id' => $supplier->id,
            'invoice_number' => 'AI-PI-' . Str::uuid(),
            'invoice_date' => now()->toDateString(),
            'discount' => 0,
            'tax' => 0,
            'status' => 'completed',
        ], [[
            'product_id' => $product->id,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'batch_number' => $batchNumber,
            'expiry_date' => $expiryDate,
        ]]);
    }
}
