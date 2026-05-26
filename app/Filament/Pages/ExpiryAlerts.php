<?php

namespace App\Filament\Pages;

use App\Models\ProductBatch;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ExpiryAlerts extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clock';

    protected static string|\UnitEnum|null $navigationGroup = 'Alerts';

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'Expiry Alerts';

    protected string $view = 'filament.pages.expiry-alerts';

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getExpiryAlertQuery())
            ->defaultSort('expiry_date')
            ->columns([
                Tables\Columns\TextColumn::make('product.name')
                    ->label('Product')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                Tables\Columns\TextColumn::make('batch_number')
                    ->label('Batch')
                    ->badge()
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('expiry_date')
                    ->label('Expiry date')
                    ->date()
                    ->sortable()
                    ->color(fn (ProductBatch $record): string => $record->expiry_date->isPast() ? 'danger' : 'warning'),

                Tables\Columns\TextColumn::make('expiry_status')
                    ->label('Status')
                    ->state(fn (ProductBatch $record): string => $record->expiry_date->isPast() ? 'Expired' : 'Expiring soon')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'Expired' ? 'danger' : 'warning'),

                Tables\Columns\TextColumn::make('days_remaining')
                    ->label('Remaining')
                    ->state(function (ProductBatch $record): string {
                        $days = now()->startOfDay()->diffInDays($record->expiry_date->startOfDay(), false);

                        if ($days < 0) {
                            return abs((int) $days) . ' days expired';
                        }

                        if ((int) $days === 0) {
                            return 'Expires today';
                        }

                        return (int) $days . ' days left';
                    })
                    ->color(fn (ProductBatch $record): string => $record->expiry_date->isPast() ? 'danger' : 'warning'),

                Tables\Columns\TextColumn::make('quantity')
                    ->label('Quantity')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('purchase_price')
                    ->label('Purchase price')
                    ->money('SYP')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\Filter::make('expired')
                    ->label('Expired only')
                    ->query(fn (Builder $query): Builder => $query->whereDate('expiry_date', '<', today())),

                Tables\Filters\Filter::make('expiring_soon')
                    ->label('Expiring soon only')
                    ->query(fn (Builder $query): Builder => $query
                        ->whereDate('expiry_date', '>=', today())
                        ->whereDate('expiry_date', '<=', today()->addDays(30))),
            ])
            ->emptyStateIcon('heroicon-o-check-circle')
            ->emptyStateHeading('No expiry alerts')
            ->emptyStateDescription('There are no active batches expired or expiring within the next 30 days.');
    }

    private function getExpiryAlertQuery(): Builder
    {
        return ProductBatch::query()
            ->with('product')
            ->where('quantity', '>', 0)
            ->whereDate('expiry_date', '<=', today()->addDays(30));
    }
}
