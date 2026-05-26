<?php

namespace App\Filament\Resources\SaleInvoiceResource\RelationManagers;

use App\Models\SaleItem;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class SaleItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'saleItems';

    protected static ?string $title = 'Sold Items';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('product.name')
                    ->label('Product')
                    ->searchable()
                    ->weight('medium'),

                Tables\Columns\TextColumn::make('productBatch.batch_number')
                    ->label('Batch')
                    ->badge()
                    ->searchable(),

                Tables\Columns\TextColumn::make('productBatch.expiry_date')
                    ->label('Expiry')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('quantity')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('unit_price')
                    ->label('Sale price')
                    ->money('EGP')
                    ->sortable(),

                Tables\Columns\TextColumn::make('purchase_price_at_sale')
                    ->label('Cost at sale')
                    ->money('EGP')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('line_profit')
                    ->label('Line profit')
                    ->state(fn (SaleItem $record): float => (float) $record->quantity * ((float) $record->unit_price - (float) $record->purchase_price_at_sale))
                    ->money('EGP')
                    ->color(fn (float $state): string => $state >= 0 ? 'success' : 'danger')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('total')
                    ->money('EGP')
                    ->sortable()
                    ->weight('bold'),
            ]);
    }

    public function isReadOnly(): bool
    {
        return true;
    }
}
