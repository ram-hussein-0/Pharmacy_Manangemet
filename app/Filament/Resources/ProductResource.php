<?php

namespace App\Filament\Resources;

use App\Models\Product;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;
    protected static string|\UnitEnum|null $navigationGroup = 'Catalog';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cube';
    protected static ?int $navigationSort = 30;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\Select::make('category_id')->relationship('category', 'name')->required(),
            Forms\Components\TextInput::make('name')->required()->maxLength(255),
            Forms\Components\TextInput::make('barcode')->required()->unique(ignoreRecord: true)->maxLength(50),
            Forms\Components\Textarea::make('description')->rows(2)->columnSpanFull(),
            Forms\Components\TextInput::make('sale_price')->numeric()->required()->minValue(0)->prefix('EGP'),
            Forms\Components\TextInput::make('minimum_stock')->numeric()->required()->minValue(0)->default(10),
            Forms\Components\Toggle::make('is_active')->default(true),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('barcode')->searchable()->fontFamily('mono')->size('xs'),
            Tables\Columns\TextColumn::make('category.name')->badge(),
            Tables\Columns\TextColumn::make('sale_price')->money('EGP')->sortable(),
            Tables\Columns\TextColumn::make('current_stock')
                ->label('Stock')
                ->badge()
                ->color(fn (Product $r) => $r->is_low_stock ? 'danger' : 'success'),
            Tables\Columns\TextColumn::make('minimum_stock')->label('Min'),
            Tables\Columns\IconColumn::make('is_active')->boolean(),
        ])->filters([
            Tables\Filters\TernaryFilter::make('is_active'),
            Tables\Filters\SelectFilter::make('category_id')->relationship('category', 'name'),
        ])->actions([
            \Filament\Actions\EditAction::make(),
            \Filament\Actions\DeleteAction::make(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => \App\Filament\Resources\ProductResource\Pages\ListProducts::route('/'),
            'create' => \App\Filament\Resources\ProductResource\Pages\CreateProduct::route('/create'),
            'edit'   => \App\Filament\Resources\ProductResource\Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
