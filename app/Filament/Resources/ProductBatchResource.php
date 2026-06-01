<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductBatchResource\Pages;
use App\Models\ProductBatch;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * READ-ONLY resource.
 *
 * Batches are produced exclusively by PurchaseInvoiceService when a purchase
 * invoice is marked completed. Manual create/edit is intentionally disabled to
 * prevent stock corruption (every batch must be tied to a purchase item and a
 * stock movement). Any future manual adjustment must go through
 * StockMovementService::recordAdjust() so an audit movement is recorded.
 */
class ProductBatchResource extends Resource
{
    protected static ?string $model = ProductBatch::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static string|\UnitEnum|null $navigationGroup = 'Catalog';
    protected static ?int $navigationSort = 4;
    protected static ?string $navigationLabel = 'Product Batches';

    public static function canCreate(): bool { return false; }
    public static function canEdit($record): bool { return false; }
    public static function canDelete($record): bool { return false; }

    public static function form(Schema $schema): Schema
    {
        // No manual editing — see class doc-block.
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('expiry_date', 'asc')
            ->columns([
                Tables\Columns\TextColumn::make('product.name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('batch_number')->searchable(),
                Tables\Columns\TextColumn::make('expiry_date')->date()->sortable()
                    ->badge()
                    ->color(fn (ProductBatch $r) => match ($r->expiry_status) {
                        'expired'  => 'danger',
                        'critical' => 'warning',
                        'warning'  => 'info',
                        default    => 'success',
                    }),
                Tables\Columns\TextColumn::make('quantity')->numeric()->sortable(),
                Tables\Columns\TextColumn::make('purchase_price')->money('SYP')->sortable(),
            ])
            ->filters([
                Tables\Filters\Filter::make('expired')
                    ->label('Expired')
                    ->query(fn ($q) => $q->whereDate('expiry_date', '<', now())),
                Tables\Filters\Filter::make('expiring_30')
                    ->label('Expiring ≤ 30 days')
                    ->query(fn ($q) => $q->whereBetween('expiry_date', [now(), now()->addDays(30)])),
                Tables\Filters\Filter::make('in_stock')
                    ->label('In stock')
                    ->query(fn ($q) => $q->where('quantity', '>', 0)),
            ])
            ->actions([\Filament\Actions\ViewAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProductBatches::route('/'),
        ];
    }
}
