<?php

namespace Tests\Feature\Reports;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReportPagesAccessTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * @return array<int, string>
     */
    private function phaseTwoPageUrls(): array
    {
        return [
            '/admin/low-stock-alerts',
            '/admin/expiry-alerts',
            '/admin/inventory-report',
            '/admin/sales-report',
            '/admin/purchase-report',
            '/admin/profit-loss-report',
        ];
    }

    public function test_admin_can_access_phase_two_report_and_alert_pages(): void
    {
        $this->actingAs($this->makeAdminUser());

        foreach ($this->phaseTwoPageUrls() as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_guest_is_redirected_from_phase_two_report_and_alert_pages(): void
    {
        foreach ($this->phaseTwoPageUrls() as $url) {
            $response = $this->get($url);

            $response->assertRedirect();

            $this->assertStringContainsString(
                '/admin/login',
                $response->headers->get('Location') ?? '',
                "Guest visiting {$url} should be redirected to the Filament login page."
            );
        }
    }

    public function test_admin_can_see_key_report_page_content(): void
    {
        $this->actingAs($this->makeAdminUser());

        $this->get('/admin/inventory-report')
            ->assertOk()
            ->assertSee('Inventory Report')
            ->assertSee('Stock value by category')
            ->assertSee('Inventory details');

        $this->get('/admin/sales-report')
            ->assertOk()
            ->assertSee('Sales Report')
            ->assertSee('Completed invoices')
            ->assertSee('Completed sales details');

        $this->get('/admin/purchase-report')
            ->assertOk()
            ->assertSee('Purchase Report')
            ->assertSee('Completed invoices')
            ->assertSee('Spend by supplier');

        $this->get('/admin/profit-loss-report')
            ->assertOk()
            ->assertSee('Profit & Loss Report')
            ->assertSee('Revenue')
            ->assertSee('Net profit');
    }

    private function makeAdminUser(): User
    {
        return User::create([
            'name' => 'Reports Test Admin',
            'email' => 'reports-admin-' . Str::uuid() . '@example.com',
            'password' => Hash::make('password'),
            'phone' => '0000000000',
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
        ]);
    }
}
