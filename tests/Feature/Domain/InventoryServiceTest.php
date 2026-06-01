<?php

namespace Tests\Feature\Domain;

use App\Models\Category;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\Supplier;
use App\Models\User;
use App\Services\InventoryService;
use App\Services\PurchaseInvoiceService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class InventoryServiceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_low_stock_uses_product_batch_quantities(): void
    {
        $this->actingAsTestUser();

        [$supplier, $product] = $this->makeSupplierAndProduct(
            label: 'low-stock',
            minimumStock: 5,
        );

        $this->createCompletedPurchase(
            product: $product,
            supplier: $supplier,
            quantity: 4,
            unitPrice: 25,
            batchNumber: 'BATCH-LOW-STOCK-' . Str::uuid(),
            expiryDate: now()->addMonths(6)->toDateString(),
        );

        $inventory = app(InventoryService::class);

        $availableStock = $inventory->availableStock($product->id);
        $lowStockProducts = $inventory->lowStockProducts();

        $this->assertSame(4, $availableStock);
        $this->assertTrue(
            $lowStockProducts->contains('id', $product->id),
            'Product should appear in low stock list when batch stock is below minimum_stock.'
        );
    }

    public function test_total_stock_value_uses_batch_quantity_times_purchase_price(): void
    {
        $this->actingAsTestUser();

        [$supplier, $product] = $this->makeSupplierAndProduct(
            label: 'stock-value',
            minimumStock: 0,
        );

        $inventory = app(InventoryService::class);

        $valueBefore = (float) $inventory->totalStockValue();

        $this->createCompletedPurchase(
            product: $product,
            supplier: $supplier,
            quantity: 4,
            unitPrice: 25,
            batchNumber: 'BATCH-STOCK-VALUE-' . Str::uuid(),
            expiryDate: now()->addMonths(6)->toDateString(),
        );

        $valueAfter = (float) $inventory->totalStockValue();

        $this->assertEquals(
            100.00,
            $valueAfter - $valueBefore,
            'Stock value should increase by quantity × purchase_price.'
        );
    }

    public function test_expiring_and_expired_batches_are_detected(): void
    {
        $this->actingAsTestUser();

        [$supplier, $product] = $this->makeSupplierAndProduct(
            label: 'expiry',
            minimumStock: 0,
        );

        $expiringBatchNumber = 'BATCH-EXPIRING-' . Str::uuid();
        $expiredBatchNumber = 'BATCH-EXPIRED-' . Str::uuid();

        $this->createCompletedPurchase(
            product: $product,
            supplier: $supplier,
            quantity: 2,
            unitPrice: 20,
            batchNumber: $expiringBatchNumber,
            expiryDate: now()->addDays(10)->toDateString(),
        );

        $this->createCompletedPurchase(
            product: $product,
            supplier: $supplier,
            quantity: 2,
            unitPrice: 20,
            batchNumber: $expiredBatchNumber,
            expiryDate: now()->subDay()->toDateString(),
        );

        $inventory = app(InventoryService::class);

        $expiring = $inventory->expiringBatches(45);
        $expired = $inventory->expiredBatches();

        $this->assertTrue(
            $expiring->contains(fn ($batch): bool => $batch->batch_number === $expiringBatchNumber),
            'Batch expiring within 45 days should appear in expiringBatches().'
        );

        $this->assertTrue(
            $expired->contains(fn ($batch): bool => $batch->batch_number === $expiredBatchNumber),
            'Past expiry batch should appear in expiredBatches().'
        );
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
    private function makeSupplierAndProduct(string $label, int $minimumStock): array
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
            'minimum_stock' => $minimumStock,
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
