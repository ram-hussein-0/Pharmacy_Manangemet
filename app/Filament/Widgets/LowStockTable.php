<?php

namespace App\Filament\Widgets;

use App\Models\Product;
use App\Models\ProductBatch;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class LowStockTable extends TableWidget
{
    protected static ?int $sort = 20;

    protected static ?string $heading = 'Low Stock Products';

    protected int|string|array $columnSpan = 'full';

    protected function getTableQuery(): Builder
    {
        return Product::query()
            ->with('category')
            ->withSum(['productBatches as batch_stock' => fn ($query) => $query->sellable()], 'quantity')
            ->havingRaw('COALESCE(batch_stock, 0) <= minimum_stock')
            ->orderBy('name');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Product')
                    ->searchable()
                    ->weight('medium'),

                Tables\Columns\TextColumn::make('category.name')
                    ->label('Category')
                    ->placeholder('No category'),

                Tables\Columns\TextColumn::make('batch_stock')
                    ->label('Current stock')
                    ->numeric()
                    ->badge()
                    ->color('warning'),

                Tables\Columns\TextColumn::make('minimum_stock')
                    ->label('Minimum')
                    ->numeric(),
            ])
            ->emptyStateHeading('No low stock products')
            ->emptyStateDescription('All products are currently above their minimum stock level.');
    }
}
