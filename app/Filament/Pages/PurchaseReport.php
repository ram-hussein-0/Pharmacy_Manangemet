<?php

namespace App\Filament\Pages;

use App\Models\PurchaseInvoice;
use App\Models\Supplier;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PurchaseReport extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-chart-bar';

    protected static string|\UnitEnum|null $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 3;

    protected static ?string $title = 'Purchase Report';

    protected string $view = 'filament.pages.purchase-report';

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getPurchaseQuery())
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

                Tables\Columns\TextColumn::make('supplier.name')
                    ->label('Supplier')
                    ->placeholder('No supplier')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('subtotal')
                    ->label('Subtotal')
                    ->money('SYP')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('discount')
                    ->label('Discount')
                    ->money('SYP')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('tax')
                    ->label('Tax')
                    ->money('SYP')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('total')
                    ->label('Total')
                    ->money('SYP')
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                    ->color(fn (string $state): string => match ($state) {
                        'completed' => 'success',
                        'cancelled' => 'danger',
                        'pending' => 'warning',
                        'draft' => 'gray',
                        default => 'gray',
                    })
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\Filter::make('completed_only')
                    ->label('Completed only')
                    ->query(fn (Builder $query): Builder => $query->where('status', 'completed')),

                Tables\Filters\Filter::make('draft_only')
                    ->label('Draft only')
                    ->query(fn (Builder $query): Builder => $query->where('status', 'draft')),

                Tables\Filters\Filter::make('this_month')
                    ->label('This month')
                    ->query(fn (Builder $query): Builder => $query
                        ->whereYear('invoice_date', now()->year)
                        ->whereMonth('invoice_date', now()->month)),

                Tables\Filters\SelectFilter::make('supplier_id')
                    ->label('Supplier')
                    ->options(fn () => Supplier::query()->orderBy('name')->pluck('name', 'id')),
            ])
            ->emptyStateIcon('heroicon-o-document-arrow-down')
            ->emptyStateHeading('No purchase invoices found')
            ->emptyStateDescription('Purchase invoices will appear here after they are created.');
    }

    private function getPurchaseQuery(): Builder
    {
        return PurchaseInvoice::query()
            ->with('supplier');
    }
}
