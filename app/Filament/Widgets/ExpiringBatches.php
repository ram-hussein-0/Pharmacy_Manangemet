<?php

namespace App\Filament\Widgets;

use App\Models\ProductBatch;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class ExpiringBatches extends TableWidget
{
    protected static ?int $sort = 30;

    protected static ?string $heading = 'Expiring Batches';

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                ProductBatch::query()
                    ->with('product')
                    ->where('quantity', '>', 0)
                    ->whereBetween('expiry_date', [today(), today()->addDays(30)])
                    ->orderBy('expiry_date')
            )
            ->columns([
                Tables\Columns\TextColumn::make('product.name')
                    ->label('Product')
                    ->searchable()
                    ->weight('medium'),

                Tables\Columns\TextColumn::make('batch_number')
                    ->label('Batch')
                    ->badge()
                    ->searchable(),

                Tables\Columns\TextColumn::make('expiry_date')
                    ->label('Expiry date')
                    ->date()
                    ->sortable()
                    ->color('danger'),

                Tables\Columns\TextColumn::make('quantity')
                    ->numeric()
                    ->sortable(),
            ])
            ->emptyStateHeading('No batches expiring soon')
            ->emptyStateDescription('There are no active batches expiring within the next 30 days.');
    }
}
