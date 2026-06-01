<?php

namespace App\Filament\ReportWidgets;

use App\Models\SaleItem;
use Filament\Widgets\ChartWidget;

class SalesRevenueByProductChart extends ChartWidget
{
    protected ?string $heading = 'Revenue by Product';

    protected ?string $description = 'Top products by completed sales revenue.';

    protected ?string $maxHeight = '420px';

    protected int|string|array $columnSpan = 'full';

    protected function getData(): array
    {
        $rows = SaleItem::query()
            ->select('products.name')
            ->selectRaw('SUM(sale_items.quantity) AS units')
            ->selectRaw('SUM(sale_items.quantity * sale_items.unit_price) AS revenue')
            ->join('sale_invoices', 'sale_invoices.id', '=', 'sale_items.sale_invoice_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->where('sale_invoices.status', 'completed')
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('revenue')
            ->limit(10)
            ->get();

        return [
            'labels' => $rows->pluck('name')->all(),
            'datasets' => [
                [
                    'label' => 'Revenue (SYP)',
                    'data' => $rows->pluck('revenue')->map(fn ($value): float => (float) $value)->all(),
                    'backgroundColor' => 'rgba(22, 163, 74, 0.82)',
                    'borderColor' => 'rgb(22, 163, 74)',
                    'borderWidth' => 2,
                    'borderRadius' => 8,
                    'barPercentage' => 0.72,
                    'categoryPercentage' => 0.78,
                ],
            ],
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => false,
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
