<?php

namespace App\Filament\Resources\PurchaseInvoiceResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class PurchaseItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'purchaseItems';

    protected static ?string $title = 'Items';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('product.name')
                    ->label('Product')
                    ->searchable(),

                Tables\Columns\TextColumn::make('batch_number')
                    ->searchable(),

                Tables\Columns\TextColumn::make('expiry_date')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('quantity')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('unit_price')
                    ->money('SYP')
                    ->sortable(),

                Tables\Columns\TextColumn::make('total')
                    ->money('SYP')
                    ->sortable(),
            ]);
    }

    public function isReadOnly(): bool
    {
        return true;
    }
}
