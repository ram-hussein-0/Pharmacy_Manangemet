<?php

namespace App\Filament\Widgets;

use App\Models\SaleInvoice;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class RecentSales extends TableWidget
{
    protected static ?string $heading = 'Recent Sales';

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                SaleInvoice::query()
                    ->with('saleItems')
                    ->latest('invoice_date')
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('invoice_number')
                    ->label('Invoice')
                    ->searchable()
                    ->weight('medium'),

                Tables\Columns\TextColumn::make('invoice_date')
                    ->label('Date')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('customer_name')
                    ->label('Customer')
                    ->placeholder('Walk-in customer'),

                Tables\Columns\TextColumn::make('total')
                    ->money('EGP')
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'completed' => 'success',
                        'cancelled' => 'danger',
                        'pending' => 'warning',
                        default => 'gray',
                    }),
            ])
            ->emptyStateHeading('No sales yet')
            ->emptyStateDescription('Completed sales will appear here.');
    }
}
