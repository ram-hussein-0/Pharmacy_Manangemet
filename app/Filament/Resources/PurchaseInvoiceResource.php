<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PurchaseInvoiceResource\Pages;
use App\Filament\Resources\PurchaseInvoiceResource\RelationManagers;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\Supplier;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class PurchaseInvoiceResource extends Resource
{
    protected static ?string $model = PurchaseInvoice::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-arrow-down';

    protected static string|\UnitEnum|null $navigationGroup = 'Purchases';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Purchase Invoice')
                ->columnSpanFull()
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('invoice_number')
                        ->label('Invoice number')
                        ->required()
                        ->maxLength(255),

                    Forms\Components\Select::make('supplier_id')
                        ->label('Supplier')
                        ->options(fn () => Supplier::query()->orderBy('name')->pluck('name', 'id'))
                        ->searchable()
                        ->required(),

                    Forms\Components\DatePicker::make('invoice_date')
                        ->label('Invoice date')
                        ->required()
                        ->default(now()),

                    Forms\Components\Placeholder::make('status_display')
                        ->label('Status')
                        ->content(fn (?PurchaseInvoice $record): string => $record?->status ?? 'draft')
                        ->visibleOn('edit'),

                    Forms\Components\Hidden::make('status')
                        ->default('draft')
                        ->visibleOn('create'),

                    Forms\Components\TextInput::make('discount')
                        ->numeric()
                        ->step('0.01')
                        ->default(0)
                        ->minValue(0),

                    Forms\Components\TextInput::make('tax')
                        ->numeric()
                        ->step('0.01')
                        ->default(0)
                        ->minValue(0),

                    Forms\Components\Repeater::make('items')
                        ->label('Invoice items')
                        ->schema([
                            Forms\Components\Select::make('product_id')
                                ->label('Product')
                                ->options(fn () => Product::query()
                                    ->where('is_active', true)
                                    ->orderBy('name')
                                    ->pluck('name', 'id'))
                                ->searchable()
                                ->required()
                                ->columnSpanFull(),

                            Forms\Components\TextInput::make('batch_number')
                                ->label('Batch number')
                                ->required()
                                ->maxLength(255),

                            Forms\Components\DatePicker::make('expiry_date')
                                ->label('Expiry date')
                                ->required(),

                            Forms\Components\TextInput::make('quantity')
                                ->numeric()
                                ->minValue(1)
                                ->required(),

                            Forms\Components\TextInput::make('unit_price')
                                ->label('Unit price')
                                ->numeric()
                                ->step('0.01')
                                ->minValue(0)
                                ->required(),
                        ])
                        ->columns(2)
                        ->minItems(1)
                        ->addActionLabel('Add item')
                        ->columnSpanFull()
                        ->visibleOn('create'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('invoice_date', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('invoice_number')
                    ->label('Invoice number')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('supplier.name')
                    ->label('Supplier')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('invoice_date')
                    ->label('Invoice date')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('total')
                    ->money('SYP')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'completed' => 'success',
                        'cancelled' => 'danger',
                        'pending' => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'pending' => 'Pending',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                    ]),

                Tables\Filters\SelectFilter::make('supplier_id')
                    ->label('Supplier')
                    ->options(fn () => Supplier::query()->orderBy('name')->pluck('name', 'id')),
            ])
            ->actions([
                \Filament\Actions\ViewAction::make(),
                \Filament\Actions\EditAction::make()
                    ->visible(fn (PurchaseInvoice $record): bool => in_array($record->status, ['draft', 'pending'], true)),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\PurchaseItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPurchaseInvoices::route('/'),
            'create' => Pages\CreatePurchaseInvoice::route('/create'),
            'view' => Pages\ViewPurchaseInvoice::route('/{record}'),
            'edit' => Pages\EditPurchaseInvoice::route('/{record}/edit'),
        ];
    }
}
