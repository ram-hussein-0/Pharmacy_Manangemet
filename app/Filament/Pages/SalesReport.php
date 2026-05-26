<?php

namespace App\Filament\Pages;

use App\Models\SaleInvoice;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SalesReport extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-pie';

    protected static string|\UnitEnum|null $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'Sales Report';

    protected string $view = 'filament.pages.sales-report';

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getSalesQuery())
            ->defaultSort('invoice_date', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('invoice_number')
                    ->label('Invoice')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                Tables\Columns\TextColumn::make('invoice_date')
                    ->label('Date')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('customer_name')
                    ->label('Customer')
                    ->placeholder('Walk-in customer')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('customer_phone')
                    ->label('Phone')
                    ->placeholder('No phone')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('payment_method')
                    ->label('Payment')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'cash' => 'Cash',
                        'card' => 'Card',
                        'transfer' => 'Bank transfer',
                        default => $state ? ucfirst($state) : 'Unknown',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'cash' => 'success',
                        'card' => 'info',
                        'transfer' => 'warning',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('subtotal')
                    ->label('Subtotal')
                    ->money('SYP')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('discount')
                    ->label('Discount')
                    ->money('SYP')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('tax')
                    ->label('Tax')
                    ->money('SYP')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('total')
                    ->label('Total')
                    ->money('SYP')
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('profit')
                    ->label('Profit')
                    ->state(fn (SaleInvoice $record): float => (float) $record->saleItems->sum(
                        fn ($item): float => (float) $item->quantity * ((float) $item->unit_price - (float) $item->purchase_price_at_sale)
                    ))
                    ->money('SYP')
                    ->color(fn (float $state): string => $state >= 0 ? 'success' : 'danger')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                    ->color(fn (string $state): string => match ($state) {
                        'completed' => 'success',
                        'cancelled' => 'danger',
                        'pending' => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\Filter::make('completed_only')
                    ->label('Completed only')
                    ->query(fn (Builder $query): Builder => $query->where('status', 'completed'))
                    ->default(),

                Tables\Filters\Filter::make('today')
                    ->label('Today')
                    ->query(fn (Builder $query): Builder => $query->whereDate('invoice_date', today())),

                Tables\Filters\Filter::make('this_month')
                    ->label('This month')
                    ->query(fn (Builder $query): Builder => $query
                        ->whereYear('invoice_date', now()->year)
                        ->whereMonth('invoice_date', now()->month)),

                Tables\Filters\SelectFilter::make('payment_method')
                    ->label('Payment method')
                    ->options([
                        'cash' => 'Cash',
                        'card' => 'Card',
                        'transfer' => 'Bank transfer',
                    ]),
            ])
            ->emptyStateIcon('heroicon-o-receipt-percent')
            ->emptyStateHeading('No sales found')
            ->emptyStateDescription('Completed sales will appear here after using the New Sale page.');
    }

    private function getSalesQuery(): Builder
    {
        return SaleInvoice::query()
            ->with('saleItems');
    }
}
