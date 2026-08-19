<?php

namespace Tests\Feature\Administration;

use App\Filament\Pages\Auth\Login as PharmacyLogin;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Widgets\AccountWidget;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductionAdminUxTest extends TestCase
{
    use DatabaseTransactions;

    public function test_admin_panel_uses_pharmacy_branding_custom_login_and_no_default_account_widget(): void
    {
        $panel = Filament::getPanel('admin');

        $this->assertSame(PharmacyLogin::class, $panel->getLoginRouteAction());
        $this->assertSame('Pharmacy Management', $panel->getBrandName());
        $this->assertNotContains(AccountWidget::class, $panel->getWidgets());
    }

    public function test_guest_login_uses_production_copy_without_development_milestone_labels(): void
    {
        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('Pharmacy Management')
            ->assertSee('Welcome back')
            ->assertSee('Sign in')
            ->assertDontSee('Phase 4')
            ->assertDontSee('Phase 4.5')
            ->assertDontSee('V3');
    }

    public function test_admin_has_account_page_and_topbar_account_actions(): void
    {
        $this->actingAs($this->makeAdmin());

        $this->get('/admin/profile')
            ->assertOk();

        $this->get('/admin')
            ->assertOk()
            ->assertSee('Account settings')
            ->assertSee('Staff Users')
            ->assertSee('AI Assistant')
            ->assertSee('Sign out');
    }

    public function test_ai_assistant_uses_production_facing_copy(): void
    {
        $this->actingAs($this->makeAdmin());

        $this->get('/admin/ai-database-assistant')
            ->assertOk()
            ->assertSee('Pharmacy AI Assistant')
            ->assertSee('Connected · Read-only')
            ->assertSee('verified read-only pharmacy data')
            ->assertDontSee('Analytical agent · read-only')
            ->assertDontSee('Intent-only mode')
            ->assertDontSee('fixed approved handlers');
    }

    private function makeAdmin(): User
    {
        return User::create([
            'name' => 'Production Admin '.Str::uuid(),
            'email' => Str::uuid().'@example.com',
            'password' => Hash::make('Password123!'),
            'phone' => '0000000000',
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
        ]);
    }
}
