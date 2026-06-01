<?php

namespace Tests\Feature\Reports;

use App\Models\Category;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\Supplier;
use App\Models\User;
use App\Services\PurchaseInvoiceService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class InventoryAlertReportDataTest extends TestCase
{
    use DatabaseTransactions;

    public function test_low_stock_alerts_show_low_stock_product(): void
    {
        $this->actingAs($this->makeAdminUser());

        [$supplier, $product] = $this->makeSupplierAndProduct(
            label: 'low-stock-alert',
            minimumStock: 10,
            salePrice: 120,
        );

        $this->createCompletedPurchase(
            product: $product,
            supplier: $supplier,
            quantity: 3,
            unitPrice: 25,
            batchNumber: 'REPORT-LOW-STOCK-BATCH-' . Str::uuid(),
            expiryDate: now()->addMonths(6)->toDateString(),
        );

        $this->get('/admin/low-stock-alerts')
            ->assertOk()
            ->assertSee($product->name)
            ->assertSee('Low stock');
    }

    public function test_expiry_alerts_show_expiring_batch(): void
    {
        $this->actingAs($this->makeAdminUser());

        [$supplier, $product] = $this->makeSupplierAndProduct(
            label: 'expiry-alert',
            minimumStock: 0,
            salePrice: 130,
        );

        $batchNumber = 'REPORT-EXPIRING-BATCH-' . Str::uuid();

        $this->createCompletedPurchase(
            product: $product,
            supplier: $supplier,
            quantity: 5,
            unitPrice: 30,
            batchNumber: $batchNumber,
            expiryDate: now()->addDays(10)->toDateString(),
        );

        $this->get('/admin/expiry-alerts')
            ->assertOk()
            ->assertSee($product->name)
            ->assertSee($batchNumber)
            ->assertSee('Expiring soon');
    }

    public function test_inventory_report_shows_stock_quantity_and_value(): void
    {
        $this->actingAs($this->makeAdminUser());

        [$supplier, $product] = $this->makeSupplierAndProduct(
            label: 'inventory-report',
            minimumStock: 2,
            salePrice: 150,
        );

        $this->createCompletedPurchase(
            product: $product,
            supplier: $supplier,
            quantity: 4,
            unitPrice: 25,
            batchNumber: 'REPORT-INVENTORY-BATCH-' . Str::uuid(),
            expiryDate: now()->addMonths(8)->toDateString(),
        );

        $this->get('/admin/inventory-report')
            ->assertOk()
            ->assertSee($product->name)
            ->assertSee('Inventory details')
            ->assertSee('SYP 100.00');
    }

    private function makeAdminUser(): User
    {
        return User::create([
            'name' => 'Reports Data Test Admin',
            'email' => 'reports-data-admin-' . Str::uuid() . '@example.com',
            'password' => Hash::make('password'),
            'phone' => '0000000000',
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
        ]);
    }

    /**
     * @return array{0: Supplier, 1: Product}
     */
    private function makeSupplierAndProduct(string $label, int $minimumStock, float $salePrice): array
    {
        $suffix = strtoupper($label) . '-' . Str::uuid();

        $category = Category::create([
            'name' => "Reports Category {$suffix}",
            'description' => "Temporary reports category {$suffix}",
        ]);

        $supplier = Supplier::create([
            'name' => "Reports Supplier {$suffix}",
            'phone' => '0000000000',
            'email' => strtolower("reports-supplier-{$suffix}@example.com"),
            'address' => 'Temporary reports address',
            'notes' => "Temporary reports supplier {$suffix}",
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'name' => "Reports Product {$suffix}",
            'barcode' => "REPORTS-BARCODE-{$suffix}",
            'description' => "Temporary reports product {$suffix}",
            'sale_price' => $salePrice,
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
            'invoice_number' => 'REPORT-PI-' . Str::uuid(),
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
