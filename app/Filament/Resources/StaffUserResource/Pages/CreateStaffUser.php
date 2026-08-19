<?php

namespace App\Filament\Resources\StaffUserResource\Pages;

use App\Filament\Resources\StaffUserResource;
use App\Models\User;
use App\Services\UserAccountService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class CreateStaffUser extends CreateRecord
{
    protected static string $resource = StaffUserResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        /** @var User $actor */
        $actor = Auth::user();

        return app(UserAccountService::class)->create($actor, $data);
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Staff account created';
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
