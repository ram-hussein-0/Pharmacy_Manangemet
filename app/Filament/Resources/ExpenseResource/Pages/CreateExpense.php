<?php

namespace App\Filament\Resources\ExpenseResource\Pages;

use App\Filament\Resources\ExpenseResource;
use App\Services\ExpenseService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class CreateExpense extends CreateRecord
{
    protected static string $resource = ExpenseResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(ExpenseService::class)->create($data, Auth::id());
    }
}
