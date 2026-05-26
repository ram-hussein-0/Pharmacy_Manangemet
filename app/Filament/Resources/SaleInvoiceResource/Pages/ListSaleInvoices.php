<?php

namespace App\Filament\Resources\SaleInvoiceResource\Pages;

use App\Filament\Pages\NewSale;
use App\Filament\Resources\SaleInvoiceResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSaleInvoices extends ListRecords
{
    protected static string $resource = SaleInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('newSale')
                ->label('New Sale')
                ->icon('heroicon-o-shopping-cart')
                ->color('primary')
                ->url(fn (): string => NewSale::getUrl()),
        ];
    }
}
