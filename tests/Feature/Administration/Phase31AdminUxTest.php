<?php

namespace Tests\Feature\Administration;

use App\Filament\Pages\AiDatabaseAssistant;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Services\UserAccountService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Tests\TestCase;

class Phase31AdminUxTest extends TestCase
{
    use DatabaseTransactions;

    public function test_entity_picker_is_closed_by_default_and_only_queries_when_open(): void
    {
        $admin = $this->makeUser('Phase31 Picker Admin', User::ROLE_ADMIN);
        $this->actingAs($admin);

        $category = Category::create([
            'name' => 'Phase31 Category '.Str::uuid(),
            'description' => null,
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Phase31 Medicine '.Str::uuid(),
            'barcode' => 'P31-'.Str::uuid(),
            'description' => null,
            'sale_price' => 100,
            'minimum_stock' => 1,
            'is_active' => true,
        ]);

        Livewire::test(AiDatabaseAssistant::class)
            ->assertSet('entityPickerOpen', false)
            ->assertDontSee($product->name)
            ->call('toggleEntityPicker')
            ->assertSet('entityPickerOpen', true)
            ->set('entitySearch', 'Phase31 Medicine')
            ->assertSee($product->name)
            ->call('insertEntity', 'product', $product->id)
            ->assertSet('question', 'product: "'.$product->name.'"')
            ->assertSet('entityPickerOpen', false)
            ->assertSet('entitySearch', '');
    }

    public function test_admin_can_open_staff_users_page_and_pharmacist_cannot(): void
    {
        $admin = $this->makeUser('Phase31 Staff Admin', User::ROLE_ADMIN);
        $pharmacist = $this->makeUser('Phase31 Pharmacist', User::ROLE_PHARMACIST);

        $this->actingAs($admin)
            ->get('/admin/staff-users')
            ->assertOk()
            ->assertSee($pharmacist->name)
            ->assertSee($pharmacist->email)
            ->assertSee('Block account');

        auth()->logout();

        $this->actingAs($pharmacist)
            ->get('/admin/staff-users')
            ->assertForbidden();
    }

    public function test_admin_can_block_and_reactivate_staff_without_deleting_history_identity(): void
    {
        $admin = $this->makeUser('Phase31 Account Admin', User::ROLE_ADMIN);
        $pharmacist = $this->makeUser('Phase31 Account Pharmacist', User::ROLE_PHARMACIST);

        Sanctum::actingAs($pharmacist);
        $pharmacist->createToken('phase31-old-token');

        $service = app(UserAccountService::class);
        $blocked = $service->deactivate($admin, $pharmacist);

        $this->assertFalse($blocked->is_active);
        $this->assertDatabaseHas('users', ['id' => $pharmacist->id, 'is_active' => 0]);
        $this->assertSame(0, $pharmacist->tokens()->count());

        $reactivated = $service->reactivate($admin, $pharmacist->fresh());
        $this->assertTrue($reactivated->is_active);
        $this->assertDatabaseHas('users', ['id' => $pharmacist->id, 'is_active' => 1]);
    }

    public function test_admin_cannot_block_self_and_non_admin_cannot_manage_accounts(): void
    {
        $admin = $this->makeUser('Phase31 Self Admin', User::ROLE_ADMIN);
        $pharmacist = $this->makeUser('Phase31 Non Admin', User::ROLE_PHARMACIST);
        $target = $this->makeUser('Phase31 Target', User::ROLE_PHARMACIST);
        $service = app(UserAccountService::class);

        try {
            $service->deactivate($admin, $admin);
            $this->fail('Expected self-blocking to be rejected.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame('You cannot block your own account.', $exception->getMessage());
        }

        try {
            $service->deactivate($pharmacist, $target);
            $this->fail('Expected non-admin account management to be rejected.');
        } catch (AuthorizationException $exception) {
            $this->assertSame('Only an active administrator can manage staff accounts.', $exception->getMessage());
        }
    }

    public function test_admin_dashboard_has_staff_users_shortcut_and_ai_page_uses_compact_picker_trigger(): void
    {
        $admin = $this->makeUser('Phase31 Dashboard Admin', User::ROLE_ADMIN);
        $this->actingAs($admin);

        $this->get('/admin')
            ->assertOk()
            ->assertSee('Staff users');

        $this->get('/admin/ai-database-assistant')
            ->assertOk()
            ->assertSee('Insert entity')
            ->assertDontSee('Insert database entity');
    }

    private function makeUser(string $name, string $role): User
    {
        return User::create([
            'name' => $name,
            'email' => Str::uuid().'@example.com',
            'password' => Hash::make('Password123!'),
            'phone' => '0000000000',
            'role' => $role,
            'is_active' => true,
        ]);
    }
}
