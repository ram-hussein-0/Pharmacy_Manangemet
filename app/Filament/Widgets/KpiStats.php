<?php

namespace App\Filament\Widgets;

use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\SaleInvoice;
use App\Services\InventoryService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class KpiStats extends BaseWidget
{
    protected ?string $heading = 'Pharmacy Overview';

    protected function getStats(): array
    {
        $inventory = app(InventoryService::class);

        $todayRevenue = (float) SaleInvoice::query()
            ->where('status', 'completed')
            ->whereDate('invoice_date', today())
            ->sum('total');

        $monthRevenue = (float) SaleInvoice::query()
            ->where('status', 'completed')
            ->whereYear('invoice_date', now()->year)
            ->whereMonth('invoice_date', now()->month)
            ->sum('total');

        $activeProducts = Product::query()->where('is_active', true)->count();
        $totalProducts = Product::query()->count();

        return [
            Stat::make('Products', $totalProducts)
                ->description("Active products: {$activeProducts}")
                ->icon('heroicon-o-cube')
                ->color('primary'),

            Stat::make('Low stock', $inventory->lowStockProducts()->count())
                ->description('Products at or below minimum stock')
                ->icon('heroicon-o-exclamation-triangle')
                ->color('warning'),

            Stat::make('Expiring soon', $inventory->expiringBatches(30)->count())
                ->description('Batches expiring within 30 days')
                ->icon('heroicon-o-clock')
                ->color('danger'),

            Stat::make('Today revenue', number_format($todayRevenue, 2) . ' EGP')
                ->description('This month: ' . number_format($monthRevenue, 2) . ' EGP')
                ->icon('heroicon-o-banknotes')
                ->color('success'),
        ];
    }
}
