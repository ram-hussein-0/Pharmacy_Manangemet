<?php

namespace App\Filament\Pages;

use App\Models\Product;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LowStockAlerts extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-exclamation-triangle';

    protected static string|\UnitEnum|null $navigationGroup = 'Alerts';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Low Stock Alerts';

    protected string $view = 'filament.pages.low-stock-alerts';

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getLowStockQuery())
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
                    ->toggleable(),

                Tables\Columns\TextColumn::make('batch_stock')
                    ->label('Current stock')
                    ->numeric()
                    ->badge()
                    ->color(fn ($state): string => ((int) $state) <= 0 ? 'danger' : 'warning')
                    ->sortable(),

                Tables\Columns\TextColumn::make('minimum_stock')
                    ->label('Minimum stock')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('stock_status')
                    ->label('Status')
                    ->state(fn (Product $record): string => ((int) ($record->batch_stock ?? 0)) <= 0 ? 'Out of stock' : 'Low stock')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'Out of stock' ? 'danger' : 'warning'),

                Tables\Columns\TextColumn::make('sale_price')
                    ->label('Sale price')
                    ->money('EGP')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\Filter::make('out_of_stock')
                    ->label('Out of stock only')
                    ->query(fn (Builder $query): Builder => $query->havingRaw('COALESCE(batch_stock, 0) <= 0')),
            ])
            ->emptyStateIcon('heroicon-o-check-circle')
            ->emptyStateHeading('No low-stock products')
            ->emptyStateDescription('All active products are currently above their minimum stock level.');
    }

    private function getLowStockQuery(): Builder
    {
        return Product::query()
            ->where('is_active', true)
            ->with('category')
            ->withSum('productBatches as batch_stock', 'quantity')
            ->havingRaw('COALESCE(batch_stock, 0) <= minimum_stock');
    }
}
