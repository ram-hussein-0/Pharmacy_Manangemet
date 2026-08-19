<?php

namespace App\Filament\Resources\SaleInvoiceResource\Pages;

use App\Filament\Resources\SaleInvoiceResource;
use App\Services\SaleInvoiceService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewSaleInvoice extends ViewRecord
{
    protected static string $resource = SaleInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('cancelSale')
                ->label('Cancel sale')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Cancel completed sale')
                ->modalDescription('This restores every sold quantity to its original batch and records reversal stock movements.')
                ->visible(fn (): bool => $this->record->status === 'completed')
                ->action(function (): void {
                    $invoice = app(SaleInvoiceService::class)->cancel($this->record);
                    $this->record->refresh();

                    Notification::make()
                        ->title("Sale {$invoice->invoice_number} cancelled")
                        ->body('Stock was restored to the original batches and reversal movements were recorded.')
                        ->success()
                        ->send();
                }),
        ];
    }
}
