<?php

namespace App\Filament\Pages;

use App\Models\Product;
use App\Models\ProductBatch;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class InventoryReport extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static string|\UnitEnum|null $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Inventory Report';

    protected string $view = 'filament.pages.inventory-report';

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getInventoryQuery())
            ->defaultSort('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Product')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                Tables\Columns\TextColumn::make('category.name')
                    ->label('Category')
                    ->placeholder('No category')
                    ->badge()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('batch_stock')
                    ->label('Current stock')
                    ->numeric()
                    ->badge()
                    ->color(fn ($state): string => match (true) {
                        (int) $state <= 0 => 'danger',
                        default => 'success',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('minimum_stock')
                    ->label('Minimum stock')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('stock_status')
                    ->label('Status')
                    ->state(function (Product $record): string {
                        $stock = (int) ($record->batch_stock ?? 0);

                        if ($stock <= 0) {
                            return 'Out of stock';
                        }

                        if ($stock <= (int) $record->minimum_stock) {
                            return 'Low stock';
                        }

                        return 'In stock';
                    })
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Out of stock' => 'danger',
                        'Low stock' => 'warning',
                        default => 'success',
                    }),

                Tables\Columns\TextColumn::make('sale_price')
                    ->label('Sale price')
                    ->money('EGP')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('stock_value')
                    ->label('Stock value')
                    ->money('EGP')
                    ->sortable()
                    ->weight('bold'),
            ])
            ->filters([
                Tables\Filters\Filter::make('active_only')
                    ->label('Active products only')
                    ->query(fn (Builder $query): Builder => $query->where('is_active', true))
                    ->default(),

                Tables\Filters\Filter::make('low_stock')
                    ->label('Low stock only')
                    ->query(fn (Builder $query): Builder => $query->havingRaw('COALESCE(batch_stock, 0) <= minimum_stock')),

                Tables\Filters\Filter::make('out_of_stock')
                    ->label('Out of stock only')
                    ->query(fn (Builder $query): Builder => $query->havingRaw('COALESCE(batch_stock, 0) <= 0')),
            ])
            ->emptyStateIcon('heroicon-o-cube')
            ->emptyStateHeading('No products found')
            ->emptyStateDescription('Inventory products will appear here after they are added.');
    }

    private function getInventoryQuery(): Builder
    {
        return Product::query()
            ->with('category')
            ->withSum('productBatches as batch_stock', 'quantity')
            ->addSelect([
                'stock_value' => ProductBatch::query()
                    ->selectRaw('COALESCE(SUM(quantity * purchase_price), 0)')
                    ->whereColumn('product_batches.product_id', 'products.id'),
            ]);
    }
}
