<?php

namespace App\Filament\Widgets;

use App\Models\SaleInvoice;
use Filament\Widgets\ChartWidget;

class SalesProfitChart extends ChartWidget
{
    protected ?string $heading = 'Sales vs Gross Profit';

    protected ?string $description = 'Last 14 days, based on completed sales only.';

    protected ?string $maxHeight = '320px';

    protected int|string|array $columnSpan = 'full';

    protected function getData(): array
    {
        $labels = [];
        $revenue = [];
        $grossProfit = [];

        for ($i = 13; $i >= 0; $i--) {
            $date = now()->subDays($i);

            $sales = SaleInvoice::query()
                ->with('saleItems')
                ->where('status', 'completed')
                ->whereDate('invoice_date', $date->toDateString())
                ->get();

            $labels[] = $date->format('M j');
            $revenue[] = (float) $sales->sum('total');

            $grossProfit[] = (float) $sales->sum(function (SaleInvoice $invoice): float {
                return (float) $invoice->saleItems->sum(
                    fn ($item): float => (float) $item->quantity * ((float) $item->unit_price - (float) $item->purchase_price_at_sale)
                );
            });
        }

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Revenue',
                    'data' => $revenue,
                    'backgroundColor' => 'rgba(59, 130, 246, 0.35)',
                    'borderColor' => 'rgb(59, 130, 246)',
                    'borderWidth' => 2,
                    'borderRadius' => 6,
                ],
                [
                    'label' => 'Gross Profit',
                    'data' => $grossProfit,
                    'backgroundColor' => 'rgba(34, 197, 94, 0.35)',
                    'borderColor' => 'rgb(34, 197, 94)',
                    'borderWidth' => 2,
                    'borderRadius' => 6,
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
