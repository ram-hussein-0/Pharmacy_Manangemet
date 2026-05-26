<?php

namespace App\Filament\Resources\ExpenseResource\Pages;

use App\Filament\Resources\ExpenseResource;
use App\Services\ExpenseService;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditExpense extends EditRecord
{
    protected static string $resource = ExpenseResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return app(ExpenseService::class)->update($record, $data);
    }
}
