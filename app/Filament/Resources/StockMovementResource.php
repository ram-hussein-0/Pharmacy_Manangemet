<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StockMovementResource\Pages;
use App\Models\StockMovement;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class StockMovementResource extends Resource
{
    protected static ?string $model = StockMovement::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static string|\UnitEnum|null $navigationGroup = 'Inventory';

    protected static ?int $navigationSort = 20;

    protected static ?string $navigationLabel = 'Stock Movements';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime('d M Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('product.name')
                    ->label('Product')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'in' => 'success',
                        'out' => 'danger',
                        'adjust' => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('quantity')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('reference_type')
                    ->label('Reference type')
                    ->badge()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('reference_id')
                    ->label('Reference')
                    ->placeholder('Manual')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('notes')
                    ->wrap()
                    ->placeholder('No notes'),

                Tables\Columns\TextColumn::make('creator.name')
                    ->label('By')
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'in' => 'In',
                        'out' => 'Out',
                        'adjust' => 'Adjustment',
                    ]),
                Tables\Filters\SelectFilter::make('reference_type')
                    ->label('Reference type')
                    ->options([
                        'purchase_invoice' => 'Purchase',
                        'sale_invoice' => 'Sale',
                        'manual' => 'Manual',
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStockMovements::route('/'),
        ];
    }
}
