<?php

namespace App\Filament\Widgets;

use App\Models\Category;
use Filament\Widgets\ChartWidget;

class StockValueByCategoryChart extends ChartWidget
{
    protected ?string $heading = 'Stock Value by Category';

    protected ?string $description = 'Calculated from remaining batch quantities and purchase prices.';

    protected ?string $maxHeight = '320px';

    protected int|string|array $columnSpan = 'full';

    protected function getData(): array
    {
        $rows = Category::query()
            ->select('categories.id', 'categories.name')
            ->selectRaw('COALESCE(SUM(product_batches.quantity * product_batches.purchase_price), 0) AS stock_value')
            ->leftJoin('products', 'products.category_id', '=', 'categories.id')
            ->leftJoin('product_batches', 'product_batches.product_id', '=', 'products.id')
            ->groupBy('categories.id', 'categories.name')
            ->orderBy('categories.name')
            ->get();

        $colors = [
            'rgba(59, 130, 246, 0.70)',
            'rgba(34, 197, 94, 0.70)',
            'rgba(245, 158, 11, 0.70)',
            'rgba(239, 68, 68, 0.70)',
            'rgba(139, 92, 246, 0.70)',
            'rgba(236, 72, 153, 0.70)',
            'rgba(6, 182, 212, 0.70)',
            'rgba(132, 204, 22, 0.70)',
        ];

        $borderColors = [
            'rgb(59, 130, 246)',
            'rgb(34, 197, 94)',
            'rgb(245, 158, 11)',
            'rgb(239, 68, 68)',
            'rgb(139, 92, 246)',
            'rgb(236, 72, 153)',
            'rgb(6, 182, 212)',
            'rgb(132, 204, 22)',
        ];

        return [
            'labels' => $rows->pluck('name')->all(),
            'datasets' => [
                [
                    'label' => 'Stock value (SYP)',
                    'data' => $rows->pluck('stock_value')
                        ->map(fn ($value): float => (float) $value)
                        ->all(),
                    'backgroundColor' => $colors,
                    'borderColor' => $borderColors,
                    'borderWidth' => 2,
                    'hoverOffset' => 8,
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
