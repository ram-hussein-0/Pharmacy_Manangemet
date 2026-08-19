<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;

class UserAccountService
{
    public function create(User $actor, array $data): User
    {
        $this->ensureAdmin($actor);

        $name = trim((string) ($data['name'] ?? ''));
        $email = mb_strtolower(trim((string) ($data['email'] ?? '')));
        $role = (string) ($data['role'] ?? '');
        $password = (string) ($data['password'] ?? '');

        if ($name === '' || $email === '') {
            throw new InvalidArgumentException('Staff name and email are required.');
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('A valid staff email address is required.');
        }

        if (User::query()->where('email', $email)->exists()) {
            throw new InvalidArgumentException('A staff account with this email already exists.');
        }

        if (! in_array($role, [User::ROLE_ADMIN, User::ROLE_PHARMACIST], true)) {
            throw new InvalidArgumentException('Unsupported staff role.');
        }

        $this->ensureStrongEnoughPassword($password);

        return DB::transaction(function () use ($name, $email, $role, $password, $data): User {
            $user = new User();
            $user->forceFill([
                'name' => $name,
                'email' => $email,
                'phone' => filled($data['phone'] ?? null) ? trim((string) $data['phone']) : null,
                'role' => $role,
                'password' => Hash::make($password),
                'is_active' => true,
            ]);
            $user->save();

            return $user->refresh();
        });
    }

    public function deactivate(User $actor, User $target): User
    {
        $this->ensureAdmin($actor);

        if ((int) $actor->getKey() === (int) $target->getKey()) {
            throw new InvalidArgumentException('You cannot block your own account.');
        }

        return DB::transaction(function () use ($target): User {
            $target->forceFill([
                'is_active' => false,
                'remember_token' => Str::random(60),
            ])->save();

            $this->revokeAccess($target);

            return $target->refresh();
        });
    }

    public function reactivate(User $actor, User $target): User
    {
        $this->ensureAdmin($actor);

        return DB::transaction(function () use ($target): User {
            $target->forceFill(['is_active' => true])->save();

            return $target->refresh();
        });
    }

    public function resetPassword(User $actor, User $target, string $password): User
    {
        $this->ensureAdmin($actor);
        $this->ensureStrongEnoughPassword($password);

        return DB::transaction(function () use ($actor, $target, $password): User {
            $target->forceFill([
                'password' => Hash::make($password),
                'remember_token' => Str::random(60),
            ])->save();

            $target->tokens()->delete();

            if ((int) $actor->getKey() !== (int) $target->getKey()) {
                $this->revokeDatabaseSessions($target);
            }

            return $target->refresh();
        });
    }

    private function revokeAccess(User $target): void
    {
        $target->tokens()->delete();
        $this->revokeDatabaseSessions($target);
    }

    private function revokeDatabaseSessions(User $target): void
    {
        if ((string) config('session.driver') !== 'database') {
            return;
        }

        $table = (string) config('session.table', 'sessions');

        if ($table === '' || preg_match('/^[A-Za-z0-9_]+$/', $table) !== 1) {
            return;
        }

        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'user_id')) {
            return;
        }

        DB::table($table)->where('user_id', $target->getKey())->delete();
    }

    private function ensureAdmin(User $actor): void
    {
        if (! $actor->is_active || ! $actor->isAdmin()) {
            throw new AuthorizationException('Only an active administrator can manage staff accounts.');
        }
    }

    private function ensureStrongEnoughPassword(string $password): void
    {
        if (mb_strlen($password) < 8) {
            throw new InvalidArgumentException('Password must contain at least 8 characters.');
        }
    }
}
