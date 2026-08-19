<?php

namespace Tests\Feature\Administration;

use App\Models\User;
use App\Services\UserAccountService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class StaffAccountManagementTest extends TestCase
{
    use DatabaseTransactions;

    public function test_active_admin_can_create_another_admin_account(): void
    {
        $actor = $this->makeUser('Account Owner', User::ROLE_ADMIN);

        $created = app(UserAccountService::class)->create($actor, [
            'name' => 'Second Administrator',
            'email' => Str::uuid().'@example.com',
            'phone' => '123456789',
            'role' => User::ROLE_ADMIN,
            'password' => 'NewAdminPassword123!',
        ]);

        $this->assertSame(User::ROLE_ADMIN, $created->role);
        $this->assertTrue($created->is_active);
        $this->assertTrue(Hash::check('NewAdminPassword123!', $created->password));
    }

    public function test_admin_can_reset_another_users_password_and_revoke_api_tokens(): void
    {
        $actor = $this->makeUser('Reset Admin', User::ROLE_ADMIN);
        $target = $this->makeUser('Reset Target', User::ROLE_PHARMACIST);

        $target->createToken('old-token');

        $updated = app(UserAccountService::class)->resetPassword($actor, $target, 'ReplacementPassword123!');

        $this->assertTrue(Hash::check('ReplacementPassword123!', $updated->password));
        $this->assertSame(0, $updated->tokens()->count());
    }

    public function test_non_admin_cannot_create_or_reset_staff_accounts(): void
    {
        $pharmacist = $this->makeUser('Non Admin', User::ROLE_PHARMACIST);
        $target = $this->makeUser('Protected Target', User::ROLE_PHARMACIST);

        try {
            app(UserAccountService::class)->create($pharmacist, [
                'name' => 'Unauthorized',
                'email' => Str::uuid().'@example.com',
                'role' => User::ROLE_ADMIN,
                'password' => 'Password123!',
            ]);
            $this->fail('Expected staff creation to require an active administrator.');
        } catch (AuthorizationException $exception) {
            $this->assertSame('Only an active administrator can manage staff accounts.', $exception->getMessage());
        }

        $this->expectException(AuthorizationException::class);
        app(UserAccountService::class)->resetPassword($pharmacist, $target, 'AnotherPassword123!');
    }

    public function test_admin_can_open_staff_creation_page_and_pharmacist_cannot(): void
    {
        $admin = $this->makeUser('Create Page Admin', User::ROLE_ADMIN);
        $this->actingAs($admin)
            ->get('/admin/staff-users/create')
            ->assertOk()
            ->assertSee('Name')
            ->assertSee('Role');

        auth()->logout();

        $pharmacist = $this->makeUser('Create Page Pharmacist', User::ROLE_PHARMACIST);
        $this->actingAs($pharmacist)
            ->get('/admin/staff-users/create')
            ->assertForbidden();
    }

    private function makeUser(string $name, string $role): User
    {
        return User::create([
            'name' => $name.' '.Str::uuid(),
            'email' => Str::uuid().'@example.com',
            'password' => Hash::make('Password123!'),
            'phone' => '0000000000',
            'role' => $role,
            'is_active' => true,
        ]);
    }
}
