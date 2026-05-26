<?php

namespace Tests\Feature\Domain;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\PurchaseInvoice;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Services\PurchaseInvoiceService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class PurchaseInvoiceServiceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_it_creates_batches_only_when_completed(): void
    {
        $this->actingAsTestUser();

        [$supplier, $product] = $this->makeSupplierAndProduct('purchase-complete');

        $batchNumber = 'BATCH-PURCHASE-' . Str::uuid();

        $invoice = app(PurchaseInvoiceService::class)->create([
            'supplier_id' => $supplier->id,
            'invoice_number' => 'TEST-DRAFT-PI-' . Str::uuid(),
            'invoice_date' => now()->toDateString(),
            'discount' => 0,
            'tax' => 0,
            'status' => 'draft',
        ], [[
            'product_id' => $product->id,
            'quantity' => 5,
            'unit_price' => 20,
            'batch_number' => $batchNumber,
            'expiry_date' => now()->addMonths(6)->toDateString(),
        ]]);

        $invoice->load('purchaseItems');

        $itemIds = $invoice->purchaseItems->pluck('id')->all();

        $this->assertSame('draft', $invoice->status);
        $this->assertCount(1, $invoice->purchaseItems);

        $this->assertSame(
            0,
            ProductBatch::whereIn('purchase_item_id', $itemIds)->count(),
            'Draft purchase invoice must not create product batches.'
        );

        $this->assertSame(
            0,
            StockMovement::where('reference_type', StockMovement::REF_PURCHASE)
                ->where('reference_id', $invoice->id)
                ->count(),
            'Draft purchase invoice must not create inbound stock movements.'
        );

        $completed = app(PurchaseInvoiceService::class)->complete($invoice->fresh());

        $batch = ProductBatch::whereIn('purchase_item_id', $itemIds)->first();

        $this->assertSame('completed', $completed->status);
        $this->assertNotNull($batch);
        $this->assertSame($product->id, (int) $batch->product_id);
        $this->assertSame($batchNumber, $batch->batch_number);
        $this->assertSame(5, (int) $batch->quantity);
        $this->assertEquals(20.00, (float) $batch->purchase_price);

        $this->assertSame(
            1,
            StockMovement::where('reference_type', StockMovement::REF_PURCHASE)
                ->where('reference_id', $invoice->id)
                ->where('type', StockMovement::TYPE_IN)
                ->count()
        );
    }

    public function test_completing_twice_is_idempotent(): void
    {
        $this->actingAsTestUser();

        [$supplier, $product] = $this->makeSupplierAndProduct('purchase-idempotent');

        $invoice = app(PurchaseInvoiceService::class)->create([
            'supplier_id' => $supplier->id,
            'invoice_number' => 'TEST-IDEMPOTENT-PI-' . Str::uuid(),
            'invoice_date' => now()->toDateString(),
            'discount' => 0,
            'tax' => 0,
            'status' => 'draft',
        ], [[
            'product_id' => $product->id,
            'quantity' => 3,
            'unit_price' => 15,
            'batch_number' => 'BATCH-IDEMPOTENT-' . Str::uuid(),
            'expiry_date' => now()->addMonths(6)->toDateString(),
        ]]);

        $invoice->load('purchaseItems');

        $itemIds = $invoice->purchaseItems->pluck('id')->all();

        app(PurchaseInvoiceService::class)->complete($invoice->fresh());

        $batchCountAfterFirstComplete = ProductBatch::whereIn('purchase_item_id', $itemIds)->count();

        $movementCountAfterFirstComplete = StockMovement::where('reference_type', StockMovement::REF_PURCHASE)
            ->where('reference_id', $invoice->id)
            ->count();

        app(PurchaseInvoiceService::class)->complete($invoice->fresh());
        app(PurchaseInvoiceService::class)->complete($invoice->fresh());

        $batchCountAfterRepeatedComplete = ProductBatch::whereIn('purchase_item_id', $itemIds)->count();

        $movementCountAfterRepeatedComplete = StockMovement::where('reference_type', StockMovement::REF_PURCHASE)
            ->where('reference_id', $invoice->id)
            ->count();

        $this->assertSame(1, $batchCountAfterFirstComplete);
        $this->assertSame(1, $movementCountAfterFirstComplete);

        $this->assertSame(
            $batchCountAfterFirstComplete,
            $batchCountAfterRepeatedComplete,
            'Repeated complete() must not duplicate product batches.'
        );

        $this->assertSame(
            $movementCountAfterFirstComplete,
            $movementCountAfterRepeatedComplete,
            'Repeated complete() must not duplicate stock movements.'
        );

        $this->assertSame('completed', $invoice->fresh()->status);
    }

    private function actingAsTestUser(): User
    {
        $user = User::create([
            'name' => 'Test Admin',
            'email' => 'test-admin-' . Str::uuid() . '@example.com',
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
            'name' => "Category {$suffix}",
            'description' => "Temporary category {$suffix}",
        ]);

        $supplier = Supplier::create([
            'name' => "Supplier {$suffix}",
            'phone' => '0000000000',
            'email' => strtolower("supplier-{$suffix}@example.com"),
            'address' => 'Temporary address',
            'notes' => "Temporary supplier {$suffix}",
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'name' => "Product {$suffix}",
            'barcode' => "BARCODE-{$suffix}",
            'description' => "Temporary product {$suffix}",
            'sale_price' => 100,
            'minimum_stock' => 5,
            'is_active' => true,
        ]);

        return [$supplier, $product];
    }
}
