<?php

namespace App\Filament\Resources\StaffUserResource\Pages;

use App\Filament\Resources\StaffUserResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListStaffUsers extends ListRecords
{
    protected static string $resource = StaffUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('addStaffUser')
                ->label('Add staff user')
                ->icon('heroicon-o-user-plus')
                ->url(fn (): string => StaffUserResource::getUrl('create')),
        ];
    }
}
