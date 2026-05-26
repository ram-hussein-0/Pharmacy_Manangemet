<?php

namespace App\Filament\Resources\PurchaseInvoiceResource\Pages;

use App\Filament\Resources\PurchaseInvoiceResource;
use App\Models\PurchaseInvoice;
use App\Services\PurchaseInvoiceService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class EditPurchaseInvoice extends EditRecord
{
    protected static string $resource = PurchaseInvoiceResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        if (! in_array($this->record->status, ['draft', 'pending'], true)) {
            Notification::make()
                ->title('This invoice is locked')
                ->body('Completed or cancelled purchase invoices cannot be edited.')
                ->warning()
                ->send();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('complete')
                ->label('Complete invoice')
                ->color('success')
                ->icon('heroicon-o-check-badge')
                ->visible(fn (): bool => in_array($this->record->status, ['draft', 'pending'], true))
                ->requiresConfirmation()
                ->action(function (): void {
                    try {
                        app(PurchaseInvoiceService::class)->complete($this->record);

                        Notification::make()
                            ->title('Purchase invoice completed')
                            ->success()
                            ->send();

                        $this->redirect(PurchaseInvoiceResource::getUrl('view', ['record' => $this->record]));
                    } catch (RuntimeException $exception) {
                        Notification::make()
                            ->title('Could not complete invoice')
                            ->body($exception->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var PurchaseInvoice $record */
        return app(PurchaseInvoiceService::class)->updateHeader($record, $data);
    }
}
