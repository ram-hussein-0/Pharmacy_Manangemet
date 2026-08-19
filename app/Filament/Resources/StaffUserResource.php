<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StaffUserResource\Pages;
use App\Models\User;
use App\Services\UserAccountService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class StaffUserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static string|\UnitEnum|null $navigationGroup = 'Administration';

    protected static ?int $navigationSort = 80;

    protected static ?string $navigationLabel = 'Staff Users';

    protected static ?string $modelLabel = 'Staff user';

    protected static ?string $pluralModelLabel = 'Staff users';

    public static function canViewAny(): bool
    {
        return Auth::check() && Auth::user()?->is_active && Auth::user()?->isAdmin();
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->required()
                ->maxLength(255),

            TextInput::make('email')
                ->email()
                ->required()
                ->maxLength(255)
                ->unique(ignoreRecord: true),

            TextInput::make('phone')
                ->tel()
                ->maxLength(50),

            Select::make('role')
                ->options([
                    User::ROLE_ADMIN => 'Admin',
                    User::ROLE_PHARMACIST => 'Pharmacist',
                ])
                ->default(User::ROLE_PHARMACIST)
                ->native(false)
                ->required(),

            TextInput::make('password')
                ->password()
                ->revealable()
                ->minLength(8)
                ->same('password_confirmation')
                ->required(),

            TextInput::make('password_confirmation')
                ->label('Confirm password')
                ->password()
                ->revealable()
                ->dehydrated(false)
                ->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('phone')
                    ->placeholder('—')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('role')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                    ->color(fn (string $state): string => $state === User::ROLE_ADMIN ? 'warning' : 'info')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('role')
                    ->options([
                        User::ROLE_ADMIN => 'Admin',
                        User::ROLE_PHARMACIST => 'Pharmacist',
                    ]),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Account status')
                    ->trueLabel('Active')
                    ->falseLabel('Blocked')
                    ->placeholder('All accounts'),
            ])
            ->recordActions([
                Action::make('resetPassword')
                    ->label('Reset password')
                    ->icon('heroicon-o-key')
                    ->color('warning')
                    ->visible(fn (User $record): bool => (int) $record->getKey() !== (int) Auth::id())
                    ->modalHeading('Reset staff password')
                    ->modalDescription('Set a new password for this account. Existing API tokens and web sessions for the account will be revoked.')
                    ->schema([
                        TextInput::make('password')
                            ->label('New password')
                            ->password()
                            ->revealable()
                            ->minLength(8)
                            ->same('password_confirmation')
                            ->required(),
                        TextInput::make('password_confirmation')
                            ->label('Confirm new password')
                            ->password()
                            ->revealable()
                            ->dehydrated(false)
                            ->required(),
                    ])
                    ->action(function (array $data, User $record): void {
                        /** @var User $actor */
                        $actor = Auth::user();
                        app(UserAccountService::class)->resetPassword($actor, $record, (string) $data['password']);

                        Notification::make()
                            ->title('Password reset')
                            ->body($record->name.' can sign in with the new password.')
                            ->success()
                            ->send();
                    }),

                Action::make('block')
                    ->label('Block account')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->visible(fn (User $record): bool => $record->is_active && (int) $record->getKey() !== (int) Auth::id())
                    ->requiresConfirmation()
                    ->modalHeading('Block staff account?')
                    ->modalDescription('The account will be disabled and its active sessions and API tokens will be revoked. Historical records are preserved.')
                    ->action(function (User $record): void {
                        /** @var User $actor */
                        $actor = Auth::user();
                        app(UserAccountService::class)->deactivate($actor, $record);

                        Notification::make()
                            ->title('Account blocked')
                            ->body($record->name.' can no longer access the system.')
                            ->success()
                            ->send();
                    }),

                Action::make('reactivate')
                    ->label('Reactivate')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (User $record): bool => ! $record->is_active)
                    ->requiresConfirmation()
                    ->action(function (User $record): void {
                        /** @var User $actor */
                        $actor = Auth::user();
                        app(UserAccountService::class)->reactivate($actor, $record);

                        Notification::make()
                            ->title('Account reactivated')
                            ->body($record->name.' can sign in again.')
                            ->success()
                            ->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStaffUsers::route('/'),
            'create' => Pages\CreateStaffUser::route('/create'),
        ];
    }
}
