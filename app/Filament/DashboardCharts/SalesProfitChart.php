<?php

namespace App\Filament\DashboardCharts;

use App\Models\SaleInvoice;
use Filament\Widgets\ChartWidget;

class SalesProfitChart extends ChartWidget
{
    protected ?string $heading = 'Sales & Profit — Last 14 Days';

    protected ?string $description = 'Completed sales only. Profit uses purchase_price_at_sale.';

    protected ?string $maxHeight = '420px';

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
                    'label' => 'Sales (SYP)',
                    'data' => $revenue,
                    'borderColor' => 'rgb(22, 163, 74)',
                    'backgroundColor' => 'rgba(22, 163, 74, 0.12)',
                    'borderWidth' => 3,
                    'tension' => 0.38,
                    'fill' => true,
                    'pointRadius' => 3,
                    'pointHoverRadius' => 6,
                ],
                [
                    'label' => 'Profit (SYP)',
                    'data' => $grossProfit,
                    'borderColor' => 'rgb(14, 165, 233)',
                    'backgroundColor' => 'rgba(14, 165, 233, 0.10)',
                    'borderWidth' => 3,
                    'tension' => 0.38,
                    'fill' => true,
                    'pointRadius' => 3,
                    'pointHoverRadius' => 6,
                ],
            ],
        ];
    }

    protected function getOptions(): array
    {
        return [
            'maintainAspectRatio' => false,
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'bottom',
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
        return 'line';
    }
}
