<?php

namespace App\Filament\Pages;

use App\Models\Expense;
use App\Models\SaleInvoice;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProfitLossReport extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-presentation-chart-line';

    protected static string|\UnitEnum|null $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 4;

    protected static ?string $title = 'Profit & Loss Report';

    protected string $view = 'filament.pages.profit-loss-report';

    public ?array $data = [];

    public float $revenue = 0.0;

    public float $grossProfit = 0.0;

    public float $expenses = 0.0;

    public float $netProfit = 0.0;

    public int $salesCount = 0;

    public int $expensesCount = 0;

    public function mount(): void
    {
        $this->form->fill([
            'from' => now()->startOfMonth()->toDateString(),
            'to' => now()->toDateString(),
        ]);

        $this->updateReport();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Report period')
                    ->description('Choose the date range used to calculate revenue, gross profit, expenses, and net profit.')
                    ->columns(2)
                    ->schema([
                        Forms\Components\DatePicker::make('from')
                            ->label('From')
                            ->required(),

                        Forms\Components\DatePicker::make('to')
                            ->label('To')
                            ->required(),
                    ]),
            ])
            ->statePath('data');
    }

    public function updateReport(): void
    {
        $state = $this->form->getState();

        $from = Carbon::parse($state['from'])->startOfDay();
        $to = Carbon::parse($state['to'])->endOfDay();

        if ($from->gt($to)) {
            Notification::make()
                ->title('Invalid date range')
                ->body('The from date must be before or equal to the to date.')
                ->danger()
                ->send();

            return;
        }

        $sales = SaleInvoice::query()
            ->with('saleItems')
            ->where('status', 'completed')
            ->whereBetween('invoice_date', [$from, $to])
            ->get();

        $this->salesCount = $sales->count();
        $this->revenue = (float) $sales->sum('total');

        $this->grossProfit = (float) $sales->sum(function (SaleInvoice $invoice): float {
            return (float) $invoice->saleItems->sum(
                fn ($item): float => (float) $item->quantity * ((float) $item->unit_price - (float) $item->purchase_price_at_sale)
            );
        });

        $expensesQuery = Expense::query()
            ->whereBetween('expense_date', [$from->toDateString(), $to->toDateString()]);

        $this->expensesCount = (clone $expensesQuery)->count();
        $this->expenses = (float) $expensesQuery->sum('amount');

        $this->netProfit = $this->grossProfit - $this->expenses;
    }

    public function money(float $value): string
    {
        return number_format($value, 2) . ' EGP';
    }
}
