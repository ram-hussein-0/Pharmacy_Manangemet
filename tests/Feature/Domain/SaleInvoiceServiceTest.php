<?php

namespace Tests\Feature\Domain;

use App\Exceptions\InsufficientStockException;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\PurchaseInvoice;
use App\Models\SaleInvoice;
use App\Models\SaleItem;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Services\PurchaseInvoiceService;
use App\Services\SaleInvoiceService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class SaleInvoiceServiceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_it_consumes_batches_using_fefo(): void
    {
        $this->actingAsTestUser();

        [$supplier, $product] = $this->makeSupplierAndProduct('fefo');

        $farBatchNumber = 'BATCH-FAR-' . Str::uuid();
        $nearBatchNumber = 'BATCH-NEAR-' . Str::uuid();

        $this->createCompletedPurchase(
            product: $product,
            supplier: $supplier,
            quantity: 7,
            unitPrice: 25,
            batchNumber: $farBatchNumber,
            expiryDate: now()->addMonths(4)->toDateString(),
        );

        $this->createCompletedPurchase(
            product: $product,
            supplier: $supplier,
            quantity: 4,
            unitPrice: 30,
            batchNumber: $nearBatchNumber,
            expiryDate: now()->addMonth()->toDateString(),
        );

        $invoice = app(SaleInvoiceService::class)->create([
            'invoice_number' => 'TEST-FEFO-SALE-' . Str::uuid(),
            'invoice_date' => now()->toDateString(),
            'customer_name' => 'FEFO Test',
            'customer_phone' => '000',
            'payment_method' => 'cash',
            'discount' => 0,
            'tax' => 0,
        ], [[
            'product_id' => $product->id,
            'quantity' => 5,
            'unit_price' => 100,
        ]]);

        $invoice->load(['saleItems.productBatch']);

        $nearSold = $invoice->saleItems->first(
            fn (SaleItem $item): bool => $item->productBatch->batch_number === $nearBatchNumber
        );

        $farSold = $invoice->saleItems->first(
            fn (SaleItem $item): bool => $item->productBatch->batch_number === $farBatchNumber
        );

        $nearBatch = ProductBatch::where('batch_number', $nearBatchNumber)->firstOrFail();
        $farBatch = ProductBatch::where('batch_number', $farBatchNumber)->firstOrFail();

        $profit = $invoice->saleItems->sum(
            fn (SaleItem $item): float => (float) $item->quantity
                * ((float) $item->unit_price - (float) $item->purchase_price_at_sale)
        );

        $this->assertSame('completed', $invoice->status);
        $this->assertCount(2, $invoice->saleItems);

        $this->assertNotNull($nearSold);
        $this->assertNotNull($farSold);

        $this->assertSame(4, (int) $nearSold->quantity);
        $this->assertSame(1, (int) $farSold->quantity);

        $this->assertSame(0, (int) $nearBatch->quantity);
        $this->assertSame(6, (int) $farBatch->quantity);

        $this->assertEquals(30.00, (float) $nearSold->purchase_price_at_sale);
        $this->assertEquals(25.00, (float) $farSold->purchase_price_at_sale);

        $this->assertEquals(355.00, $profit);

        $this->assertSame(
            2,
            StockMovement::where('reference_type', StockMovement::REF_SALE)
                ->where('reference_id', $invoice->id)
                ->count()
        );
    }

    public function test_it_throws_when_stock_is_insufficient_without_side_effects(): void
    {
        $this->actingAsTestUser();

        [$supplier, $product] = $this->makeSupplierAndProduct('insufficient');

        $this->createCompletedPurchase(
            product: $product,
            supplier: $supplier,
            quantity: 3,
            unitPrice: 20,
            batchNumber: 'BATCH-INSUFFICIENT-' . Str::uuid(),
            expiryDate: now()->addMonths(6)->toDateString(),
        );

        $invoiceNumber = 'TEST-INSUFFICIENT-' . Str::uuid();

        $stockBefore = (int) ProductBatch::where('product_id', $product->id)->sum('quantity');
        $saleInvoicesBefore = SaleInvoice::count();
        $saleItemsBefore = SaleItem::count();
        $stockMovementsBefore = StockMovement::count();

        try {
            app(SaleInvoiceService::class)->create([
                'invoice_number' => $invoiceNumber,
                'invoice_date' => now()->toDateString(),
                'customer_name' => 'Insufficient Test',
                'customer_phone' => '000',
                'payment_method' => 'cash',
                'discount' => 0,
                'tax' => 0,
            ], [[
                'product_id' => $product->id,
                'quantity' => 999,
                'unit_price' => 100,
            ]]);

            $this->fail('Expected InsufficientStockException was not thrown.');
        } catch (InsufficientStockException $exception) {
            $this->assertStringContainsString('requested 999', $exception->getMessage());
        }

        $stockAfter = (int) ProductBatch::where('product_id', $product->id)->sum('quantity');

        $this->assertSame($stockBefore, $stockAfter);
        $this->assertSame($saleInvoicesBefore, SaleInvoice::count());
        $this->assertSame($saleItemsBefore, SaleItem::count());
        $this->assertSame($stockMovementsBefore, StockMovement::count());
        $this->assertFalse(SaleInvoice::where('invoice_number', $invoiceNumber)->exists());
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
            'invoice_number' => 'TEST-PI-' . Str::uuid(),
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
