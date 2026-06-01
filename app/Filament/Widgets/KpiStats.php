<?php

namespace App\Filament\Widgets;

use App\Models\Expense;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\SaleInvoice;
use App\Models\SaleItem;
use App\Models\StockMovement;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class KpiStats extends BaseWidget
{
    protected static ?int $sort = 10;

    protected int|string|array $columnSpan = 'full';

    protected function getColumns(): int|array
    {
        return [
            'default' => 1,
            'md' => 2,
            'xl' => 4,
        ];
    }

    protected function getStats(): array
    {
        $totalProducts = Product::query()->count();
        $activeProducts = Product::query()->where('is_active', true)->count();

        $lowStockProducts = Product::query()
            ->where('is_active', true)
            ->withSum('productBatches as batch_stock', 'quantity')
            ->get()
            ->filter(fn (Product $product): bool => (int) ($product->batch_stock ?? 0) <= (int) $product->minimum_stock)
            ->count();

        $expiringWithin90Days = ProductBatch::query()
            ->where('quantity', '>', 0)
            ->whereDate('expiry_date', '>=', today())
            ->whereDate('expiry_date', '<=', today()->addDays(90))
            ->count();

        $expiredBatches = ProductBatch::query()
            ->where('quantity', '>', 0)
            ->whereDate('expiry_date', '<', today())
            ->count();

        $todaySales = (float) SaleInvoice::query()
            ->where('status', 'completed')
            ->whereDate('invoice_date', today())
            ->sum('total');

        $todayInvoices = SaleInvoice::query()
            ->where('status', 'completed')
            ->whereDate('invoice_date', today())
            ->count();

        $monthlySales = (float) SaleInvoice::query()
            ->where('status', 'completed')
            ->whereYear('invoice_date', now()->year)
            ->whereMonth('invoice_date', now()->month)
            ->sum('total');

        $monthlyInvoices = SaleInvoice::query()
            ->where('status', 'completed')
            ->whereYear('invoice_date', now()->year)
            ->whereMonth('invoice_date', now()->month)
            ->count();

        $estimatedProfit = (float) SaleItem::query()
            ->join('sale_invoices', 'sale_invoices.id', '=', 'sale_items.sale_invoice_id')
            ->where('sale_invoices.status', 'completed')
            ->whereYear('sale_invoices.invoice_date', now()->year)
            ->whereMonth('sale_invoices.invoice_date', now()->month)
            ->selectRaw('COALESCE(SUM(sale_items.quantity * (sale_items.unit_price - COALESCE(sale_items.purchase_price_at_sale, 0))), 0) AS profit')
            ->value('profit');

        $totalExpenses = (float) Expense::query()->sum('amount');

        $stockMovements = StockMovement::query()->count();

        return [
            Stat::make('Total products', number_format($totalProducts))
                ->description($activeProducts . ' active')
                ->descriptionIcon('heroicon-o-cube')
                ->color('info'),

            Stat::make('Low stock', number_format($lowStockProducts))
                ->description('Below minimum')
                ->descriptionIcon('heroicon-o-exclamation-triangle')
                ->color($lowStockProducts > 0 ? 'warning' : 'success'),

            Stat::make('Expiring (90d)', number_format($expiringWithin90Days))
                ->description($expiredBatches . ' already expired')
                ->descriptionIcon('heroicon-o-calendar')
                ->color($expiredBatches > 0 ? 'danger' : 'warning'),

            Stat::make("Today's sales", $this->money($todaySales))
                ->description($todayInvoices . ' invoices')
                ->descriptionIcon('heroicon-o-shopping-cart')
                ->color('success'),

            Stat::make('Monthly sales', $this->money($monthlySales))
                ->description($monthlyInvoices . ' invoices')
                ->descriptionIcon('heroicon-o-banknotes')
                ->color('info'),

            Stat::make('Estimated profit', $this->money($estimatedProfit))
                ->description('This month, captured cost')
                ->descriptionIcon('heroicon-o-arrow-trending-up')
                ->color($estimatedProfit >= 0 ? 'success' : 'danger'),

            Stat::make('Total expenses', $this->money($totalExpenses))
                ->description('All time')
                ->descriptionIcon('heroicon-o-wallet')
                ->color('warning'),

            Stat::make('Stock movements', number_format($stockMovements))
                ->description('Audit log entries')
                ->descriptionIcon('heroicon-o-arrows-right-left')
                ->color('gray'),
        ];
    }

    private function money(float $value): string
    {
        return 'SYP ' . number_format($value, 2);
    }
}
