<?php

namespace Tests\Feature\Domain;

use App\Models\Expense;
use App\Models\User;
use App\Services\ExpenseService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class ExpenseServiceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_it_creates_expense_with_authenticated_user(): void
    {
        $user = $this->actingAsTestUser();

        $expense = app(ExpenseService::class)->create([
            'title' => 'Test Expense',
            'type' => 'other',
            'amount' => 123.45,
            'expense_date' => now()->toDateString(),
            'notes' => 'Created from domain test',
            'unexpected_field' => 'must be ignored',
        ]);

        $this->assertInstanceOf(Expense::class, $expense);
        $this->assertSame($user->id, (int) $expense->created_by);
        $this->assertSame('Test Expense', $expense->title);
        $this->assertSame('other', $expense->type);
        $this->assertEquals(123.45, (float) $expense->amount);
        $this->assertSame('Created from domain test', $expense->notes);
        $this->assertFalse(array_key_exists('unexpected_field', $expense->getAttributes()));
    }

    public function test_it_creates_expense_with_explicit_user_id(): void
    {
        $user = $this->makeUser();

        $expense = app(ExpenseService::class)->create([
            'title' => 'Explicit User Expense',
            'type' => 'rent',
            'amount' => 50,
            'expense_date' => now()->toDateString(),
            'notes' => null,
        ], $user->id);

        $this->assertSame($user->id, (int) $expense->created_by);
        $this->assertSame('Explicit User Expense', $expense->title);
        $this->assertSame('rent', $expense->type);
        $this->assertEquals(50.00, (float) $expense->amount);
    }

    public function test_it_updates_allowed_fields_without_changing_creator(): void
    {
        $originalUser = $this->actingAsTestUser();
        $otherUser = $this->makeUser();

        $expense = app(ExpenseService::class)->create([
            'title' => 'Old Expense',
            'type' => 'other',
            'amount' => 100,
            'expense_date' => now()->toDateString(),
            'notes' => 'Old notes',
        ]);

        $updated = app(ExpenseService::class)->update($expense, [
            'created_by' => $otherUser->id,
            'title' => 'Updated Expense',
            'type' => 'utilities',
            'amount' => 175.25,
            'expense_date' => now()->addDay()->toDateString(),
            'notes' => 'Updated notes',
            'unexpected_field' => 'must be ignored',
        ]);

        $this->assertSame($originalUser->id, (int) $updated->created_by);
        $this->assertSame('Updated Expense', $updated->title);
        $this->assertSame('utilities', $updated->type);
        $this->assertEquals(175.25, (float) $updated->amount);
        $this->assertSame('Updated notes', $updated->notes);
        $this->assertFalse(array_key_exists('unexpected_field', $updated->getAttributes()));
    }

    public function test_it_requires_authenticated_user_when_no_user_is_passed(): void
    {
        Auth::logout();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot create an expense without an authenticated user.');

        app(ExpenseService::class)->create([
            'title' => 'Unauthenticated Expense',
            'type' => 'other',
            'amount' => 10,
            'expense_date' => now()->toDateString(),
            'notes' => null,
        ]);
    }

    private function actingAsTestUser(): User
    {
        $user = $this->makeUser();

        Auth::login($user);

        return $user;
    }

    private function makeUser(): User
    {
        return User::create([
            'name' => 'Test User',
            'email' => 'test-user-' . Str::uuid() . '@example.com',
            'password' => Hash::make('password'),
            'phone' => '0000000000',
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
        ]);
    }
}
