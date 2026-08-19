<?php

namespace Tests\Feature\Hardening;

use App\Models\Category;
use App\Models\Expense;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseItem;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Services\ExpenseService;
use App\Services\InventoryService;
use App\Services\PurchaseInvoiceService;
use App\Services\SaleInvoiceService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class Phase2HardeningTest extends TestCase
{
    use DatabaseTransactions;

    public function test_phpunit_runs_on_the_isolated_mysql_database(): void
    {
        $this->assertSame('testing', app()->environment());
        $this->assertSame('mysql', config('database.default'));
        $this->assertSame('pharmacy_system_test', DB::connection()->getDatabaseName());
    }

    public function test_filament_panel_is_admin_only(): void
    {
        $admin = $this->makeUser('panel-admin', User::ROLE_ADMIN);
        $pharmacist = $this->makeUser('panel-pharmacist', User::ROLE_PHARMACIST);
        $inactiveAdmin = $this->makeUser('panel-inactive-admin', User::ROLE_ADMIN, false);

        $this->actingAs($admin)
            ->get('/admin')
            ->assertOk();

        Auth::logout();

        $this->actingAs($pharmacist)
            ->get('/admin')
            ->assertForbidden();

        Auth::logout();

        $this->actingAs($inactiveAdmin)
            ->get('/admin')
            ->assertForbidden();
    }

    public function test_user_management_api_requires_authentication_and_admin(): void
    {
        $this->getJson('/api/users')->assertUnauthorized();

        $pharmacist = $this->makeUser('api-pharmacist', User::ROLE_PHARMACIST);
        Sanctum::actingAs($pharmacist);
        $this->getJson('/api/users')->assertForbidden();

        $inactiveAdmin = $this->makeUser('api-inactive-admin', User::ROLE_ADMIN, false);
        Sanctum::actingAs($inactiveAdmin);
        $this->getJson('/api/users')->assertForbidden();

        $admin = $this->makeUser('api-admin', User::ROLE_ADMIN);
        Sanctum::actingAs($admin);
        $this->getJson('/api/users')
            ->assertOk()
            ->assertJsonFragment(['email' => $admin->email]);
    }

    public function test_api_login_issues_a_token_only_for_an_active_user_with_valid_credentials(): void
    {
        $active = $this->makeUser('login-active', User::ROLE_PHARMACIST, true, 'Password123!');

        $this->postJson('/api/login', [
            'email' => $active->email,
            'password' => 'Password123!',
        ])
            ->assertOk()
            ->assertJsonStructure(['status', 'token', 'token_type', 'user' => ['id', 'name', 'email', 'role', 'is_active']]);

        $this->postJson('/api/login', [
            'email' => $active->email,
            'password' => 'wrong-password',
        ])->assertUnauthorized();

        $inactive = $this->makeUser('login-inactive', User::ROLE_PHARMACIST, false, 'Password123!');

        $this->postJson('/api/login', [
            'email' => $inactive->email,
            'password' => 'Password123!',
        ])->assertForbidden();
    }

    public function test_delete_user_endpoint_deactivates_instead_of_destroying_audit_identity(): void
    {
        $admin = $this->makeUser('deactivate-admin', User::ROLE_ADMIN);
        $target = $this->makeUser('deactivate-target', User::ROLE_PHARMACIST);
        Sanctum::actingAs($admin);

        $this->deleteJson('/api/users/delete/'.$target->id)
            ->assertOk()
            ->assertJsonFragment(['message' => 'User deactivated successfully.']);

        $this->assertDatabaseHas('users', [
            'id' => $target->id,
            'is_active' => 0,
        ]);

        $this->deleteJson('/api/users/delete/'.$admin->id)
            ->assertStatus(422);

        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'is_active' => 1,
        ]);
    }

    public function test_expired_batches_are_physical_stock_but_not_sellable_stock_or_sellable_value(): void
    {
        $admin = $this->makeUser('stock-admin', User::ROLE_ADMIN);
        Auth::login($admin);

        [$supplier, $product] = $this->makeSupplierAndProduct('sellable-stock', 5);

        $this->createBatch($product, $supplier, 10, 7.50, now()->subDay()->toDateString(), 'EXPIRED');
        $this->createBatch($product, $supplier, 3, 20.00, now()->addMonths(3)->toDateString(), 'VALID');

        $product->refresh();

        $this->assertSame(13, $product->physical_stock);
        $this->assertSame(3, $product->current_stock);
        $this->assertTrue($product->is_low_stock);

        $inventory = app(InventoryService::class);
        $this->assertSame(3, $inventory->availableStock($product->id));

        $expectedValue = (float) ProductBatch::query()
            ->sellable()
            ->where('product_id', $product->id)
            ->selectRaw('COALESCE(SUM(quantity * purchase_price), 0) AS value')
            ->value('value');

        $this->assertEquals(60.00, $expectedValue);

        $this->actingAs($admin)
            ->get('/admin/low-stock-alerts')
            ->assertOk()
            ->assertSee($product->name);
    }

    public function test_stock_movement_page_uses_real_schema_fields_and_relationships(): void
    {
        $admin = $this->makeUser('movement-admin', User::ROLE_ADMIN);
        Auth::login($admin);

        [, $product] = $this->makeSupplierAndProduct('movement-resource');

        StockMovement::create([
            'product_id' => $product->id,
            'created_by' => $admin->id,
            'type' => StockMovement::TYPE_IN,
            'quantity' => 4,
            'reference_type' => StockMovement::REF_MANUAL,
            'reference_id' => null,
            'notes' => 'Phase 2 movement notes',
        ]);

        $this->actingAs($admin)
            ->get('/admin/stock-movements')
            ->assertOk()
            ->assertSee($product->name)
            ->assertSee('Phase 2 movement notes')
            ->assertSee($admin->name);
    }

    public function test_sale_cancellation_restores_the_exact_original_batches_once(): void
    {
        $admin = $this->makeUser('cancel-admin', User::ROLE_ADMIN);
        Auth::login($admin);

        [$supplier, $product] = $this->makeSupplierAndProduct('sale-cancel', 0);

        $earlyBatchNumber = 'CANCEL-EARLY-'.Str::uuid();
        $lateBatchNumber = 'CANCEL-LATE-'.Str::uuid();

        $this->createCompletedPurchase($product, $supplier, 2, 10, now()->addMonth()->toDateString(), $earlyBatchNumber);
        $this->createCompletedPurchase($product, $supplier, 4, 20, now()->addMonths(2)->toDateString(), $lateBatchNumber);

        $sale = app(SaleInvoiceService::class)->create([
            'invoice_number' => 'CANCEL-SALE-'.Str::uuid(),
            'invoice_date' => now()->toDateString(),
            'customer_name' => 'Cancellation Test',
            'customer_phone' => '000',
            'payment_method' => 'cash',
            'discount' => 0,
            'tax' => 0,
        ], [[
            'product_id' => $product->id,
            'quantity' => 5,
            'unit_price' => 100,
        ]]);

        $early = ProductBatch::query()->where('batch_number', $earlyBatchNumber)->firstOrFail();
        $late = ProductBatch::query()->where('batch_number', $lateBatchNumber)->firstOrFail();

        $this->actingAs($admin)
            ->get('/admin/sale-invoices/'.$sale->id)
            ->assertOk()
            ->assertSee('Cancel sale');

        $this->assertSame(0, (int) $early->quantity);
        $this->assertSame(1, (int) $late->quantity);

        $cancelled = app(SaleInvoiceService::class)->cancel($sale->fresh());

        $this->assertSame('cancelled', $cancelled->status);
        $this->assertSame(2, (int) $early->fresh()->quantity);
        $this->assertSame(4, (int) $late->fresh()->quantity);

        $reversalCount = StockMovement::query()
            ->where('reference_type', StockMovement::REF_SALE)
            ->where('reference_id', $sale->id)
            ->where('type', StockMovement::TYPE_IN)
            ->count();

        $this->assertSame(2, $reversalCount);

        app(SaleInvoiceService::class)->cancel($sale->fresh());

        $this->assertSame(2, (int) $early->fresh()->quantity);
        $this->assertSame(4, (int) $late->fresh()->quantity);
        $this->assertSame(
            $reversalCount,
            StockMovement::query()
                ->where('reference_type', StockMovement::REF_SALE)
                ->where('reference_id', $sale->id)
                ->where('type', StockMovement::TYPE_IN)
                ->count()
        );
    }

    public function test_expense_service_enforces_the_canonical_business_types_and_non_negative_amounts(): void
    {
        $admin = $this->makeUser('expense-admin', User::ROLE_ADMIN);

        $valid = app(ExpenseService::class)->create([
            'title' => 'Maintenance expense',
            'type' => 'maintenance',
            'amount' => 12.50,
            'expense_date' => now()->toDateString(),
            'notes' => 'Valid canonical type',
        ], $admin);

        $this->assertSame('maintenance', $valid->type);

        try {
            app(ExpenseService::class)->create([
                'title' => 'Legacy invalid type',
                'type' => 'salaries',
                'amount' => 10,
                'expense_date' => now()->toDateString(),
            ], $admin);
            $this->fail('Expected invalid expense type to be rejected.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame('Invalid expense type.', $exception->getMessage());
        }

        try {
            app(ExpenseService::class)->create([
                'title' => 'Negative expense',
                'type' => Expense::TYPES[0],
                'amount' => -1,
                'expense_date' => now()->toDateString(),
            ], $admin);
            $this->fail('Expected negative expense amount to be rejected.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame('Expense amount cannot be negative.', $exception->getMessage());
        }
    }

    private function makeUser(
        string $label,
        string $role,
        bool $active = true,
        string $password = 'Password123!'
    ): User {
        return User::create([
            'name' => 'Phase 2 '.$label,
            'email' => $label.'-'.Str::uuid().'@example.com',
            'password' => Hash::make($password),
            'phone' => '0000000000',
            'role' => $role,
            'is_active' => $active,
        ]);
    }

    /** @return array{0: Supplier, 1: Product} */
    private function makeSupplierAndProduct(string $label, int $minimumStock = 0): array
    {
        $suffix = strtoupper($label).'-'.Str::uuid();

        $category = Category::create([
            'name' => 'Phase 2 Category '.$suffix,
            'description' => 'Temporary Phase 2 category',
        ]);

        $supplier = Supplier::create([
            'name' => 'Phase 2 Supplier '.$suffix,
            'phone' => '0000000000',
            'email' => strtolower('phase2-'.$suffix.'@example.com'),
            'address' => 'Temporary address',
            'notes' => 'Temporary Phase 2 supplier',
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Phase 2 Product '.$suffix,
            'barcode' => 'PHASE2-BARCODE-'.$suffix,
            'description' => 'Temporary Phase 2 product',
            'sale_price' => 100,
            'minimum_stock' => $minimumStock,
            'is_active' => true,
        ]);

        return [$supplier, $product];
    }

    private function createBatch(
        Product $product,
        Supplier $supplier,
        int $quantity,
        float $price,
        string $expiryDate,
        string $label
    ): ProductBatch {
        $invoice = PurchaseInvoice::create([
            'supplier_id' => $supplier->id,
            'created_by' => Auth::id(),
            'invoice_number' => 'PHASE2-DIRECT-PI-'.Str::uuid(),
            'invoice_date' => now()->toDateString(),
            'subtotal' => $quantity * $price,
            'discount' => 0,
            'tax' => 0,
            'total' => $quantity * $price,
            'status' => 'completed',
        ]);

        $item = PurchaseItem::create([
            'purchase_invoice_id' => $invoice->id,
            'product_id' => $product->id,
            'quantity' => $quantity,
            'unit_price' => $price,
            'total' => $quantity * $price,
            'batch_number' => $label.'-'.Str::uuid(),
            'expiry_date' => $expiryDate,
        ]);

        return ProductBatch::create([
            'product_id' => $product->id,
            'purchase_item_id' => $item->id,
            'batch_number' => $item->batch_number,
            'expiry_date' => $expiryDate,
            'quantity' => $quantity,
            'purchase_price' => $price,
        ]);
    }

    private function createCompletedPurchase(
        Product $product,
        Supplier $supplier,
        int $quantity,
        float $unitPrice,
        string $expiryDate,
        string $batchNumber
    ): PurchaseInvoice {
        return app(PurchaseInvoiceService::class)->create([
            'supplier_id' => $supplier->id,
            'invoice_number' => 'PHASE2-PI-'.Str::uuid(),
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
