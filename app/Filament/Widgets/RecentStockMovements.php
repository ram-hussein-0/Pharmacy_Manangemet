<?php

namespace App\Filament\Widgets;

use App\Models\StockMovement;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class RecentStockMovements extends TableWidget
{
    protected static ?string $heading = 'Recent Stock Movements';

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                StockMovement::query()
                    ->with('product')
                    ->latest()
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('product.name')
                    ->label('Product')
                    ->searchable()
                    ->weight('medium'),

                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'in' => 'success',
                        'out' => 'danger',
                        'adjust' => 'warning',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('quantity')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('notes')
                    ->limit(40)
                    ->toggleable(),
            ])
            ->emptyStateHeading('No stock movements yet')
            ->emptyStateDescription('Purchase and sale movements will appear here.');
    }
}
