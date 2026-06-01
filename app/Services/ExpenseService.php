<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

class ExpenseService
{
    public function create(array $data, User|int|null $user = null): Expense
    {
        return DB::transaction(function () use ($data, $user) {
            $data['created_by'] = $this->resolveUserId($user);

            return Expense::create($this->onlyAllowedFields($data));
        });
    }

    public function update(Expense $expense, array $data): Expense
    {
        return DB::transaction(function () use ($expense, $data) {
            unset($data['created_by']);

            $expense->update($this->onlyAllowedFields($data));

            return $expense->refresh();
        });
    }

    private function onlyAllowedFields(array $data): array
    {
        return array_intersect_key($data, array_flip([
            'created_by',
            'title',
            'type',
            'amount',
            'expense_date',
            'notes',
        ]));
    }

    private function resolveUserId(User|int|null $user): int
    {
        if ($user instanceof User) {
            return (int) $user->getKey();
        }

        if (is_int($user)) {
            return $user;
        }

        $authId = Auth::id();

        if ($authId === null) {
            throw new RuntimeException('Cannot create an expense without an authenticated user.');
        }

        return (int) $authId;
    }
}
