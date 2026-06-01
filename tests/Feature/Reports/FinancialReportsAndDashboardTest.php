<?php

namespace Tests\Feature\Reports;

use App\Models\Category;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\Supplier;
use App\Models\User;
use App\Services\ExpenseService;
use App\Services\PurchaseInvoiceService;
use App\Services\SaleInvoiceService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class FinancialReportsAndDashboardTest extends TestCase
{
    use DatabaseTransactions;

    public function test_sales_report_shows_completed_sale_revenue_and_profit(): void
    {
        $this->actingAs($this->makeAdminUser());

        [$supplier, $product] = $this->makeSupplierAndProduct(
            label: 'sales-report',
            minimumStock: 0,
            salePrice: 100,
        );

        $this->createCompletedPurchase(
            product: $product,
            supplier: $supplier,
            quantity: 5,
            unitPrice: 30,
            batchNumber: 'FIN-SALES-BATCH-' . Str::uuid(),
            expiryDate: now()->addMonths(6)->toDateString(),
        );

        $invoiceNumber = 'FIN-SALES-INVOICE-' . Str::uuid();

        app(SaleInvoiceService::class)->create([
            'invoice_number' => $invoiceNumber,
            'invoice_date' => now()->toDateString(),
            'customer_name' => 'Financial Sales Customer',
            'customer_phone' => '000',
            'payment_method' => 'cash',
            'discount' => 0,
            'tax' => 0,
        ], [[
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 100,
        ]]);

        $this->get('/admin/sales-report')
            ->assertOk()
            ->assertSee($invoiceNumber)
            ->assertSee('Financial Sales Customer')
            ->assertSee('SYP 200.00')
            ->assertSee('SYP 140.00');
    }

    public function test_purchase_report_shows_completed_purchase_totals(): void
    {
        $this->actingAs($this->makeAdminUser());

        [$supplier, $product] = $this->makeSupplierAndProduct(
            label: 'purchase-report',
            minimumStock: 0,
            salePrice: 100,
        );

        $invoice = $this->createCompletedPurchase(
            product: $product,
            supplier: $supplier,
            quantity: 4,
            unitPrice: 25,
            batchNumber: 'FIN-PURCHASE-BATCH-' . Str::uuid(),
            expiryDate: now()->addMonths(6)->toDateString(),
        );

        $this->get('/admin/purchase-report')
            ->assertOk()
            ->assertSee($invoice->invoice_number)
            ->assertSee($supplier->name)
            ->assertSee('SYP 100.00');
    }

    public function test_profit_loss_report_calculates_revenue_profit_expenses_and_net_profit(): void
    {
        $this->actingAs($this->makeAdminUser());

        [$supplier, $product] = $this->makeSupplierAndProduct(
            label: 'profit-loss',
            minimumStock: 0,
            salePrice: 100,
        );

        $this->createCompletedPurchase(
            product: $product,
            supplier: $supplier,
            quantity: 5,
            unitPrice: 30,
            batchNumber: 'FIN-PROFIT-BATCH-' . Str::uuid(),
            expiryDate: now()->addMonths(6)->toDateString(),
        );

        app(SaleInvoiceService::class)->create([
            'invoice_number' => 'FIN-PROFIT-SALE-' . Str::uuid(),
            'invoice_date' => now()->toDateString(),
            'customer_name' => 'Profit Customer',
            'customer_phone' => '000',
            'payment_method' => 'cash',
            'discount' => 0,
            'tax' => 0,
        ], [[
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 100,
        ]]);

        app(ExpenseService::class)->create([
            'title' => 'Profit Loss Test Expense',
            'type' => 'other',
            'amount' => 50,
            'expense_date' => now()->toDateString(),
            'notes' => 'Temporary expense for report test',
        ]);

        $this->get('/admin/profit-loss-report')
            ->assertOk()
            ->assertSee('SYP 200.00')
            ->assertSee('SYP 140.00')
            ->assertSee('SYP 50.00')
            ->assertSee('SYP 90.00');
    }

    public function test_dashboard_route_loads_and_phase_two_widget_classes_are_available(): void
    {
        $this->actingAs($this->makeAdminUser());

        $this->get('/admin')->assertOk();

        $this->assertTrue(class_exists(\App\Filament\Widgets\KpiStats::class));
        $this->assertTrue(class_exists(\App\Filament\Widgets\DashboardCharts::class));
        $this->assertTrue(class_exists(\App\Filament\DashboardCharts\SalesProfitChart::class));
        $this->assertTrue(class_exists(\App\Filament\Widgets\LowStockTable::class));
        $this->assertTrue(class_exists(\App\Filament\Widgets\ExpiringBatches::class));

        $this->assertTrue(view()->exists('filament.widgets.dashboard-charts'));
    }

    private function makeAdminUser(): User
    {
        return User::create([
            'name' => 'Financial Reports Test Admin',
            'email' => 'financial-reports-admin-' . Str::uuid() . '@example.com',
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
            'name' => "Financial Category {$suffix}",
            'description' => "Temporary financial category {$suffix}",
        ]);

        $supplier = Supplier::create([
            'name' => "Financial Supplier {$suffix}",
            'phone' => '0000000000',
            'email' => strtolower("financial-supplier-{$suffix}@example.com"),
            'address' => 'Temporary financial address',
            'notes' => "Temporary financial supplier {$suffix}",
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'name' => "Financial Product {$suffix}",
            'barcode' => "FINANCIAL-BARCODE-{$suffix}",
            'description' => "Temporary financial product {$suffix}",
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
            'invoice_number' => 'FIN-PI-' . Str::uuid(),
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
