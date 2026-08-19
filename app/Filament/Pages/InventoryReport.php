<?php

namespace App\Filament\Pages;

use App\Models\Category;
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

    public int $productsCount = 0;
    public int $unitsInStock = 0;
    public int $lowStockCount = 0;
    public float $totalStockValue = 0.0;
    public array $categoryValues = [];

    public function mount(): void
    {
        $this->loadSummary();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getInventoryQuery())
            ->defaultSort('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Product')->searchable()->sortable()->weight('medium'),
                Tables\Columns\TextColumn::make('category.name')->label('Category')->placeholder('No category')->badge()->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('batch_stock')->label('Current stock')->numeric()->badge()->color(fn ($state): string => ((int) $state) <= 0 ? 'danger' : 'success')->sortable(),
                Tables\Columns\TextColumn::make('minimum_stock')->label('Minimum stock')->numeric()->sortable(),
                Tables\Columns\TextColumn::make('stock_status')
                    ->label('Status')
                    ->state(function (Product $record): string {
                        $stock = (int) ($record->batch_stock ?? 0);
                        if ($stock <= 0) return 'Out of stock';
                        if ($stock <= (int) $record->minimum_stock) return 'Low stock';
                        return 'In stock';
                    })
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Out of stock' => 'danger',
                        'Low stock' => 'warning',
                        default => 'success',
                    }),
                Tables\Columns\TextColumn::make('sale_price')->label('Sale price')->money('SYP')->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('stock_value')->label('Stock value')->money('SYP')->sortable()->weight('bold'),
            ])
            ->filters([
                Tables\Filters\Filter::make('active_only')->label('Active products only')->query(fn (Builder $query): Builder => $query->where('is_active', true))->default(),
                Tables\Filters\Filter::make('low_stock')->label('Low stock only')->query(fn (Builder $query): Builder => $query->havingRaw('COALESCE(batch_stock, 0) <= minimum_stock')),
                Tables\Filters\Filter::make('out_of_stock')->label('Out of stock only')->query(fn (Builder $query): Builder => $query->havingRaw('COALESCE(batch_stock, 0) <= 0')),
            ])
            ->emptyStateIcon('heroicon-o-cube')
            ->emptyStateHeading('No products found')
            ->emptyStateDescription('Inventory products will appear here after they are added.');
    }

    private function getInventoryQuery(): Builder
    {
        return Product::query()
            ->with('category')
            ->withSum(['productBatches as batch_stock' => fn ($query) => $query->sellable()], 'quantity')
            ->addSelect([
                'stock_value' => ProductBatch::query()
                    ->sellable()
                    ->selectRaw('COALESCE(SUM(quantity * purchase_price), 0)')
                    ->whereColumn('product_batches.product_id', 'products.id'),
            ]);
    }

    private function loadSummary(): void
    {
        $products = Product::query()
            ->where('is_active', true)
            ->withSum(['productBatches as batch_stock' => fn ($query) => $query->sellable()], 'quantity')
            ->get();

        $this->productsCount = $products->count();
        $this->unitsInStock = (int) ProductBatch::query()->sellable()->sum('quantity');
        $this->totalStockValue = (float) ProductBatch::query()->sellable()->selectRaw('COALESCE(SUM(quantity * purchase_price), 0) AS value')->value('value');

        $this->lowStockCount = $products
            ->filter(fn (Product $product): bool => (int) ($product->batch_stock ?? 0) <= (int) $product->minimum_stock)
            ->count();

        $rows = Category::query()
            ->select('categories.name')
            ->selectRaw('COALESCE(SUM(product_batches.quantity * product_batches.purchase_price), 0) AS stock_value')
            ->leftJoin('products', 'products.category_id', '=', 'categories.id')
            ->leftJoin('product_batches', 'product_batches.product_id', '=', 'products.id')
            ->where('product_batches.quantity', '>', 0)
            ->whereDate('product_batches.expiry_date', '>=', today())
            ->groupBy('categories.id', 'categories.name')
            ->orderByDesc('stock_value')
            ->limit(8)
            ->get();

        $max = max((float) $rows->max('stock_value'), 1);

        $this->categoryValues = $rows
            ->map(fn ($row): array => [
                'name' => $row->name,
                'value' => (float) $row->stock_value,
                'percentage' => (int) round(((float) $row->stock_value / $max) * 100),
            ])
            ->all();
    }

    public function money(float $value): string
    {
        return 'SYP ' . number_format($value, 2);
    }
}
