<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductBatchResource\Pages;
use App\Models\ProductBatch;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * READ-ONLY resource.
 *
 * Batches are produced exclusively by PurchaseInvoiceService when a purchase
 * invoice is marked completed. Manual create/edit is intentionally disabled to
 * prevent stock corruption.
 */
class ProductBatchResource extends Resource
{
    protected static ?string $model = ProductBatch::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static string|\UnitEnum|null $navigationGroup = 'Catalog';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Product Batches';

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
            ->defaultSort('expiry_date', 'asc')
            ->columns([
                Tables\Columns\TextColumn::make('product.name')
                    ->label('Product')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                Tables\Columns\TextColumn::make('batch_number')
                    ->label('Batch')
                    ->searchable()
                    ->badge(),

                Tables\Columns\TextColumn::make('expiry_date')
                    ->label('Expiry date')
                    ->date()
                    ->sortable()
                    ->badge()
                    ->color(fn (ProductBatch $record): string => match ($record->expiry_status) {
                        'expired' => 'danger',
                        'critical' => 'warning',
                        'warning' => 'info',
                        default => 'success',
                    }),

                Tables\Columns\TextColumn::make('quantity')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('purchase_price')
                    ->money('SYP')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\Filter::make('expired')
                    ->label('Expired')
                    ->query(fn (Builder $query): Builder => $query
                        ->whereDate('expiry_date', '<', today())),

                Tables\Filters\Filter::make('expiring_30')
                    ->label('Expiring ≤ 30 days')
                    ->query(fn (Builder $query): Builder => $query
                        ->whereDate('expiry_date', '>=', today())
                        ->whereDate('expiry_date', '<=', today()->addDays(30))),

                Tables\Filters\Filter::make('in_stock')
                    ->label('In stock')
                    ->query(fn (Builder $query): Builder => $query
                        ->where('quantity', '>', 0)),
            ])
            ->actions([
                \Filament\Actions\ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProductBatches::route('/'),
        ];
    }
}
