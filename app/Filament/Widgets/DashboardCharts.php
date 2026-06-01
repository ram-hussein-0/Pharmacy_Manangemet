<?php

namespace App\Filament\Widgets;

use App\Models\SaleItem;
use Filament\Widgets\Widget;

class DashboardCharts extends Widget
{
    protected static ?int $sort = 65;

    protected string $view = 'filament.widgets.dashboard-charts';

    protected int|string|array $columnSpan = 'full';

    protected function getViewData(): array
    {
        $rows = SaleItem::query()
            ->select('products.name')
            ->selectRaw('SUM(sale_items.quantity) AS units')
            ->join('sale_invoices', 'sale_invoices.id', '=', 'sale_items.sale_invoice_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->where('sale_invoices.status', 'completed')
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('units')
            ->limit(8)
            ->get();

        $maxUnits = max((int) $rows->max('units'), 1);

        return [
            'topProducts' => $rows
                ->map(fn ($row): array => [
                    'name' => $row->name,
                    'units' => (int) $row->units,
                    'percentage' => (int) round(((int) $row->units / $maxUnits) * 100),
                ])
                ->all(),
        ];
    }
}
