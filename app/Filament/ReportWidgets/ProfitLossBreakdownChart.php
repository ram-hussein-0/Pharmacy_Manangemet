<?php

namespace App\Filament\ReportWidgets;

use App\Models\Expense;
use App\Models\SaleInvoice;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;

class ProfitLossBreakdownChart extends ChartWidget
{
    protected ?string $heading = 'Profit & Loss Breakdown';

    protected ?string $description = 'Gross profit, expenses, and net profit for the selected period.';

    protected ?string $maxHeight = '420px';

    protected int|string|array $columnSpan = 'full';

    public ?string $from = null;

    public ?string $to = null;

    protected function getData(): array
    {
        $from = $this->from
            ? Carbon::parse($this->from)->startOfDay()
            : now()->startOfMonth();

        $to = $this->to
            ? Carbon::parse($this->to)->endOfDay()
            : now()->endOfDay();

        $sales = SaleInvoice::query()
            ->with('saleItems')
            ->where('status', 'completed')
            ->whereBetween('invoice_date', [$from->toDateString(), $to->toDateString()])
            ->get();

        $grossProfit = (float) $sales->sum(function (SaleInvoice $invoice): float {
            return (float) $invoice->saleItems->sum(
                fn ($item): float => (float) $item->quantity * ((float) $item->unit_price - (float) $item->purchase_price_at_sale)
            );
        });

        $expenses = (float) Expense::query()
            ->whereBetween('expense_date', [$from->toDateString(), $to->toDateString()])
            ->sum('amount');

        $netProfit = $grossProfit - $expenses;

        return [
            'labels' => ['Gross profit', 'Expenses', 'Net profit'],
            'datasets' => [
                [
                    'label' => 'Amount (SYP)',
                    'data' => [$grossProfit, $expenses, $netProfit],
                    'backgroundColor' => [
                        'rgba(22, 163, 74, 0.80)',
                        'rgba(220, 38, 38, 0.80)',
                        $netProfit >= 0 ? 'rgba(37, 99, 235, 0.80)' : 'rgba(249, 115, 22, 0.80)',
                    ],
                    'borderColor' => [
                        'rgb(22, 163, 74)',
                        'rgb(220, 38, 38)',
                        $netProfit >= 0 ? 'rgb(37, 99, 235)' : 'rgb(249, 115, 22)',
                    ],
                    'borderWidth' => 2,
                    'borderRadius' => 8,
                    'barPercentage' => 0.55,
                    'categoryPercentage' => 0.65,
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
                    'beginAtZero' => false,
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
