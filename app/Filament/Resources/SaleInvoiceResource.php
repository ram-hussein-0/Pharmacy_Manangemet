<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SaleInvoiceResource\Pages;
use App\Filament\Resources\SaleInvoiceResource\RelationManagers;
use App\Models\SaleInvoice;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SaleInvoiceResource extends Resource
{
    protected static ?string $model = SaleInvoice::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-arrow-up';

    protected static string|\UnitEnum|null $navigationGroup = 'Sales';

    protected static ?int $navigationSort = 1;

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

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['saleItems']);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('invoice_date', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('invoice_number')
                    ->label('Invoice')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                Tables\Columns\TextColumn::make('invoice_date')
                    ->label('Date')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('customer_name')
                    ->label('Customer')
                    ->placeholder('Walk-in customer')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('payment_method')
                    ->label('Payment')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'cash' => 'Cash',
                        'card' => 'Card',
                        'transfer' => 'Bank transfer',
                        default => $state ? ucfirst($state) : 'Unknown',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'cash' => 'success',
                        'card' => 'info',
                        'transfer' => 'warning',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('total')
                    ->label('Total')
                    ->money('SYP')
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('profit')
                    ->label('Profit')
                    ->state(fn (SaleInvoice $record): float => (float) $record->saleItems->sum(
                        fn ($item): float => (float) $item->quantity * ((float) $item->unit_price - (float) $item->purchase_price_at_sale)
                    ))
                    ->money('SYP')
                    ->color(fn (float $state): string => $state >= 0 ? 'success' : 'danger')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
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
                        'completed' => 'Completed',
                        'pending' => 'Pending',
                        'cancelled' => 'Cancelled',
                    ]),

                Tables\Filters\SelectFilter::make('payment_method')
                    ->label('Payment method')
                    ->options([
                        'cash' => 'Cash',
                        'card' => 'Card',
                        'transfer' => 'Bank transfer',
                    ]),

                Tables\Filters\Filter::make('today')
                    ->label('Today')
                    ->query(fn (Builder $query): Builder => $query->whereDate('invoice_date', today())),
            ])
            ->actions([
                \Filament\Actions\ViewAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\SaleItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSaleInvoices::route('/'),
            'view' => Pages\ViewSaleInvoice::route('/{record}'),
        ];
    }
}
