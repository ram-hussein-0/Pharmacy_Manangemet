<?php

namespace Tests\Feature\Ai;

use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Ai\AiAnalyticalAnswerer;
use App\Services\Ai\AiAnalyticalAssistantService;
use App\Services\Ai\AiAnalyticalExecutor;
use App\Services\Ai\AiAnalyticalPlanner;
use App\Services\Ai\AiAnalyticalPlanValidator;
use App\Services\Ai\AiBusinessSemantics;
use App\Services\Ai\AiDatabaseAssistantService;
use App\Services\Ai\AiPlanValidationException;
use App\Services\Ai\AiSchemaCatalog;
use App\Services\Ai\LlmClientService;
use App\Services\PurchaseInvoiceService;
use App\Services\SaleInvoiceService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class Phase4AnalyticalAgentTest extends TestCase
{
    use DatabaseTransactions;

    public function test_schema_catalog_is_dynamic_and_excludes_security_and_personal_contact_columns(): void
    {
        $catalog = app(AiSchemaCatalog::class)->catalog();

        $this->assertArrayHasKey('products', $catalog['tables']);
        $this->assertArrayHasKey('sale_items', $catalog['tables']);
        $this->assertArrayNotHasKey('personal_access_tokens', $catalog['tables']);
        $this->assertArrayNotHasKey('sessions', $catalog['tables']);
        $this->assertArrayNotHasKey('password', $catalog['tables']['users']['columns']);
        $this->assertArrayNotHasKey('email', $catalog['tables']['users']['columns']);
        $this->assertArrayNotHasKey('customer_phone', $catalog['tables']['sale_invoices']['columns']);

        $this->assertNotNull(
            app(AiSchemaCatalog::class)->relationshipBetween('sale_items.sale_invoice_id', 'sale_invoices.id')
        );
    }

    public function test_validator_rejects_unverified_schema_and_join_claims(): void
    {
        $validator = app(AiAnalyticalPlanValidator::class);

        $this->expectException(AiPlanValidationException::class);

        $validator->validate('show sales', [
            'answerable' => true,
            'queries' => [[
                'id' => 'bad_join',
                'from' => 'sale_items',
                'joins' => [[
                    'table' => 'suppliers',
                    'left' => 'sale_items.product_id',
                    'right' => 'suppliers.id',
                    'type' => 'inner',
                ]],
                'select' => [[
                    'kind' => 'column',
                    'column' => 'suppliers.name',
                    'alias' => 'supplier',
                ]],
                'filters' => [],
                'group_by' => [],
                'order_by' => [],
                'limit' => 20,
            ]],
            'calculations' => [],
            'display_query' => 'bad_join',
        ]);
    }

    public function test_validator_rejects_protected_customer_contact_data(): void
    {
        $validator = app(AiAnalyticalPlanValidator::class);

        $this->expectException(AiPlanValidationException::class);

        $validator->validate('show customer phone', [
            'answerable' => true,
            'queries' => [[
                'id' => 'pii',
                'from' => 'sale_invoices',
                'joins' => [],
                'select' => [[
                    'kind' => 'column',
                    'column' => 'sale_invoices.customer_phone',
                    'alias' => 'phone',
                ]],
                'filters' => [],
                'group_by' => [],
                'order_by' => [],
                'limit' => 20,
            ]],
            'calculations' => [],
            'display_query' => 'pii',
        ]);
    }

    public function test_validator_accepts_verified_fk_join_with_new_table_on_either_side(): void
    {
        $validator = app(AiAnalyticalPlanValidator::class);

        $base = [
            'answerable' => true,
            'reason' => '',
            'calculations' => [],
            'display_query' => 'joined_invoice',
        ];

        $newTableOnRight = $base + [
            'queries' => [[
                'id' => 'joined_invoice',
                'from' => 'sale_items',
                'joins' => [[
                    'table' => 'sale_invoices',
                    'left' => 'sale_items.sale_invoice_id',
                    'right' => 'sale_invoices.id',
                    'type' => 'inner',
                ]],
                'select' => [[
                    'kind' => 'column',
                    'column' => 'sale_invoices.invoice_number',
                    'alias' => 'invoice_number',
                ]],
                'filters' => [],
                'group_by' => [],
                'order_by' => [],
                'limit' => 20,
            ]],
        ];

        $rightValidated = $validator->validate('show sale invoice numbers', $newTableOnRight);
        $this->assertSame('sale_invoices', $rightValidated['queries'][0]['joins'][0]['table']);

        $newTableOnLeft = $newTableOnRight;
        $newTableOnLeft['queries'][0]['joins'][0]['left'] = 'sale_invoices.id';
        $newTableOnLeft['queries'][0]['joins'][0]['right'] = 'sale_items.sale_invoice_id';

        $leftValidated = $validator->validate('show sale invoice numbers', $newTableOnLeft);
        $this->assertSame('sale_invoices', $leftValidated['queries'][0]['joins'][0]['table']);
    }

    public function test_count_distinct_can_use_safe_text_columns_without_becoming_a_capability_gate(): void
    {
        $plan = app(AiAnalyticalPlanValidator::class)->validate('count distinct product names', [
            'answerable' => true,
            'reason' => '',
            'queries' => [[
                'id' => 'distinct_products',
                'from' => 'products',
                'joins' => [],
                'select' => [[
                    'kind' => 'aggregate',
                    'function' => 'count_distinct',
                    'expression' => ['type' => 'column', 'column' => 'products.name'],
                    'alias' => 'distinct_products',
                ]],
                'filters' => [],
                'group_by' => [],
                'order_by' => [],
                'limit' => 1,
            ]],
            'calculations' => [],
            'display_query' => 'distinct_products',
        ]);

        $this->assertSame('count_distinct', $plan['queries'][0]['select'][0]['function']);
        $this->assertSame('products.name', $plan['queries'][0]['select'][0]['expression']['column']);
    }

    public function test_deepseek_advanced_payload_uses_thinking_reasoning_and_json_mode_without_temperature(): void
    {
        \Illuminate\Support\Facades\Http::fake([
            'https://api.deepseek.com/chat/completions' => \Illuminate\Support\Facades\Http::response([
                'choices' => [[
                    'message' => ['content' => '{"answerable":false}'],
                ]],
            ], 200),
        ]);

        $llm = new LlmClientService(
            apiKey: 'phase4-deepseek-test-key',
            model: 'deepseek-v4-flash',
            provider: 'openai_compatible',
            baseUrl: 'https://api.deepseek.com',
        );

        $result = $llm->completeAdvanced(
            systemPrompt: 'Return JSON only.',
            userPrompt: 'Return a test JSON object.',
            jsonMode: true,
            options: [
                'thinking' => true,
                'reasoning_effort' => 'high',
                'max_tokens' => 512,
            ],
        );

        $this->assertSame('{"answerable":false}', $result);

        \Illuminate\Support\Facades\Http::assertSent(function ($request): bool {
            $data = $request->data();

            return $request->url() === 'https://api.deepseek.com/chat/completions'
                && ($data['model'] ?? null) === 'deepseek-v4-flash'
                && data_get($data, 'thinking.type') === 'enabled'
                && ($data['reasoning_effort'] ?? null) === 'high'
                && ($data['max_tokens'] ?? null) === 512
                && data_get($data, 'response_format.type') === 'json_object'
                && ! array_key_exists('temperature', $data);
        });
    }

    public function test_executor_runs_verified_aggregate_ast_without_model_sql(): void
    {
        $admin = $this->actingAsAdmin();
        [$supplier, $product] = $this->makeSupplierAndProduct();
        $this->createPurchaseAndSale($supplier, $product);

        $plan = app(AiAnalyticalPlanValidator::class)->validate('gross profit by product', $this->profitPlan());
        $evidence = app(AiAnalyticalExecutor::class)->execute($plan);
        $rows = $evidence['queries']['profit_by_product'];

        $match = collect($rows)->firstWhere('product', $product->name);

        $this->assertNotNull($match);
        $this->assertEquals(40.0, (float) $match['gross_profit']);
        $this->assertSame($admin->id, Auth::id());
    }

    public function test_primary_assistant_uses_free_analytical_plan_instead_of_legacy_intent_gate(): void
    {
        config([
            'llm.api_key' => 'phase4-test-key',
            'llm.provider' => 'openai_compatible',
            'llm.base_url' => 'https://api.deepseek.com',
            'llm.model' => 'deepseek-v4-flash',
        ]);

        $this->actingAsAdmin();
        [$supplier, $product] = $this->makeSupplierAndProduct();
        $this->createPurchaseAndSale($supplier, $product);

        $fakeLlm = new class([$this->profitPlanJson(), 'This product generated a verified gross profit of 40.']) extends LlmClientService {
            public function __construct(private array $responses)
            {
            }

            public function completeAdvanced(string $systemPrompt, string $userPrompt, bool $jsonMode = false, array $options = []): string
            {
                if ($this->responses === []) {
                    throw new RuntimeException('Unexpected extra LLM call.');
                }

                return array_shift($this->responses);
            }
        };

        $catalog = app(AiSchemaCatalog::class);
        $semantics = app(AiBusinessSemantics::class);
        $validator = new AiAnalyticalPlanValidator($catalog, $semantics);
        $planner = new AiAnalyticalPlanner($fakeLlm, $catalog, $semantics, $validator);
        $answerer = new AiAnalyticalAnswerer($fakeLlm);
        $executor = new AiAnalyticalExecutor();

        $legacy = new class extends AiDatabaseAssistantService {
            public function __construct()
            {
            }

            public function ask(string $question): array
            {
                throw new RuntimeException('Legacy intent route must not gate a valid Phase 4 plan.');
            }
        };

        $service = new AiAnalyticalAssistantService($planner, $executor, $answerer, $legacy);
        $result = $service->ask('Which medicine made the strongest gross profit contribution?');

        $this->assertSame('analytical_agent', $result['intent']);
        $this->assertSame('This product generated a verified gross profit of 40.', $result['answer']);
        $this->assertContains('gross_profit', $result['columns']);
        $this->assertTrue(collect($result['rows'])->contains(fn (array $row): bool => $row['product'] === $product->name));
    }

    public function test_deterministic_calculations_combine_independent_scalar_queries_without_fanout(): void
    {
        $validator = app(AiAnalyticalPlanValidator::class);
        $executor = app(AiAnalyticalExecutor::class);

        $plan = $validator->validate('net profit', [
            'answerable' => true,
            'queries' => [
                [
                    'id' => 'sales_profit',
                    'from' => 'sale_items',
                    'joins' => [[
                        'table' => 'sale_invoices',
                        'left' => 'sale_items.sale_invoice_id',
                        'right' => 'sale_invoices.id',
                        'type' => 'inner',
                    ]],
                    'select' => [[
                        'kind' => 'aggregate',
                        'function' => 'sum',
                        'expression' => [
                            'type' => 'binary',
                            'operator' => '*',
                            'left' => ['type' => 'column', 'column' => 'sale_items.quantity'],
                            'right' => [
                                'type' => 'binary',
                                'operator' => '-',
                                'left' => ['type' => 'column', 'column' => 'sale_items.unit_price'],
                                'right' => ['type' => 'column', 'column' => 'sale_items.purchase_price_at_sale'],
                            ],
                        ],
                        'alias' => 'gross_profit',
                    ]],
                    'filters' => [[
                        'column' => 'sale_invoices.status',
                        'operator' => '=',
                        'value' => 'completed',
                        'source' => 'semantic',
                    ]],
                    'group_by' => [],
                    'order_by' => [],
                    'limit' => 1,
                ],
                [
                    'id' => 'expense_total',
                    'from' => 'expenses',
                    'joins' => [],
                    'select' => [[
                        'kind' => 'aggregate',
                        'function' => 'sum',
                        'expression' => ['type' => 'column', 'column' => 'expenses.amount'],
                        'alias' => 'expenses',
                    ]],
                    'filters' => [],
                    'group_by' => [],
                    'order_by' => [],
                    'limit' => 1,
                ],
            ],
            'calculations' => [[
                'alias' => 'net_profit',
                'operator' => 'subtract',
                'left' => ['query' => 'sales_profit', 'field' => 'gross_profit'],
                'right' => ['query' => 'expense_total', 'field' => 'expenses'],
            ]],
            'display_query' => 'sales_profit',
        ]);

        $evidence = $executor->execute($plan);

        $this->assertArrayHasKey('net_profit', $evidence['calculations']);
        $this->assertIsFloat((float) $evidence['calculations']['net_profit']);
    }

    private function actingAsAdmin(): User
    {
        $user = User::create([
            'name' => 'Phase 4 Admin '.Str::uuid(),
            'email' => Str::uuid().'@example.com',
            'password' => Hash::make('Password123!'),
            'phone' => '0000000000',
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
        ]);

        Auth::login($user);

        return $user;
    }

    /** @return array{0:Supplier,1:Product} */
    private function makeSupplierAndProduct(): array
    {
        $suffix = Str::uuid();
        $category = Category::create(['name' => 'Phase4 Category '.$suffix, 'description' => null]);
        $supplier = Supplier::create([
            'name' => 'Phase4 Supplier '.$suffix,
            'phone' => '0000000000',
            'email' => null,
            'address' => null,
            'notes' => null,
        ]);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Phase4 Medicine '.$suffix,
            'barcode' => 'P4-'.$suffix,
            'description' => null,
            'sale_price' => 30,
            'minimum_stock' => 1,
            'is_active' => true,
        ]);

        return [$supplier, $product];
    }

    private function createPurchaseAndSale(Supplier $supplier, Product $product): void
    {
        app(PurchaseInvoiceService::class)->create([
            'supplier_id' => $supplier->id,
            'invoice_number' => 'P4-PI-'.Str::uuid(),
            'invoice_date' => now()->toDateString(),
            'discount' => 0,
            'tax' => 0,
            'status' => 'completed',
        ], [[
            'product_id' => $product->id,
            'quantity' => 5,
            'unit_price' => 10,
            'batch_number' => 'P4-BATCH-'.Str::uuid(),
            'expiry_date' => now()->addMonths(6)->toDateString(),
        ]]);

        app(SaleInvoiceService::class)->create([
            'invoice_number' => 'P4-SALE-'.Str::uuid(),
            'invoice_date' => now(),
            'payment_method' => 'cash',
            'discount' => 0,
            'tax' => 0,
        ], [[
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 30,
        ]]);
    }

    private function profitPlan(): array
    {
        return [
            'answerable' => true,
            'reason' => '',
            'queries' => [[
                'id' => 'profit_by_product',
                'from' => 'sale_items',
                'joins' => [
                    [
                        'table' => 'sale_invoices',
                        'left' => 'sale_items.sale_invoice_id',
                        'right' => 'sale_invoices.id',
                        'type' => 'inner',
                    ],
                    [
                        'table' => 'products',
                        'left' => 'sale_items.product_id',
                        'right' => 'products.id',
                        'type' => 'inner',
                    ],
                ],
                'select' => [
                    ['kind' => 'column', 'column' => 'products.name', 'alias' => 'product'],
                    [
                        'kind' => 'aggregate',
                        'function' => 'sum',
                        'expression' => [
                            'type' => 'binary',
                            'operator' => '*',
                            'left' => ['type' => 'column', 'column' => 'sale_items.quantity'],
                            'right' => [
                                'type' => 'binary',
                                'operator' => '-',
                                'left' => ['type' => 'column', 'column' => 'sale_items.unit_price'],
                                'right' => ['type' => 'column', 'column' => 'sale_items.purchase_price_at_sale'],
                            ],
                        ],
                        'alias' => 'gross_profit',
                    ],
                ],
                'filters' => [[
                    'column' => 'sale_invoices.status',
                    'operator' => '=',
                    'value' => 'completed',
                    'source' => 'semantic',
                ]],
                'group_by' => ['products.name'],
                'order_by' => [['field' => 'gross_profit', 'direction' => 'desc']],
                'limit' => 20,
            ]],
            'calculations' => [],
            'display_query' => 'profit_by_product',
        ];
    }

    private function profitPlanJson(): string
    {
        return json_encode($this->profitPlan(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
    }
}
