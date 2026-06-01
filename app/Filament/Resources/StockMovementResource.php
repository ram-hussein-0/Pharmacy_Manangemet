<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StockMovementResource\Pages;
use App\Models\StockMovement;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Schemas\Schema;

class StockMovementResource extends Resource
{
    protected static ?string $model = StockMovement::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrows-up-down';
    protected static string|\UnitEnum|null $navigationGroup = 'Inventory';
    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema { return $schema->components([]); /* read-only */ }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('product.name')->searchable(),
                Tables\Columns\TextColumn::make('productBatch.batch_number')->label('Batch'),
                Tables\Columns\BadgeColumn::make('type')->colors([
                    'success' => 'in', 'danger' => 'out', 'warning' => 'adjust',
                ]),
                Tables\Columns\TextColumn::make('quantity')->numeric(),
                Tables\Columns\TextColumn::make('reason')->wrap(),
                Tables\Columns\TextColumn::make('user.name')->label('By')->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')->options([
                    'in' => 'In', 'out' => 'Out', 'adjust' => 'Adjust',
                ]),
            ]);
    }

    public static function canCreate(): bool { return false; }
    public static function canEdit($record): bool { return false; }
    public static function canDelete($record): bool { return false; }

    public static function getPages(): array
    {
        return ['index' => Pages\ListStockMovements::route('/')];
    }
}
