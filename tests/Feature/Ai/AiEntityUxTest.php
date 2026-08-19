<?php

namespace Tests\Feature\Ai;

use App\Filament\Pages\AiDatabaseAssistant;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Ai\AiDatabaseAssistantService;
use App\Services\Ai\IntentClassifier;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class AiEntityUxTest extends TestCase
{
    use DatabaseTransactions;

    public function test_tagged_entity_questions_are_classified_without_an_llm(): void
    {
        config(['llm.api_key' => null]);

        $classifier = app(IntentClassifier::class);

        $this->assertSame(
            ['intent' => 'product_lookup', 'params' => ['product_name' => 'Panadol Extra']],
            $classifier->classify('product: "Panadol Extra"'),
        );
        $this->assertSame(
            ['intent' => 'supplier_lookup', 'params' => ['supplier_name' => 'Medi Supply']],
            $classifier->classify('supplier: "Medi Supply"'),
        );
        $this->assertSame(
            ['intent' => 'staff_lookup', 'params' => ['staff_name' => 'Rana Ahmad']],
            $classifier->classify('staff: "Rana Ahmad"'),
        );
        $this->assertSame(
            ['intent' => 'category_lookup', 'params' => ['category_name' => 'Pain Relief']],
            $classifier->classify('category: "Pain Relief"'),
        );
    }

    public function test_ai_uses_safe_fuzzy_fallback_for_misspelled_named_entities(): void
    {
        config(['llm.api_key' => null]);
        $this->actingAs($this->makeUser('Resolver Admin', User::ROLE_ADMIN));

        $category = Category::create(['name' => 'Pain Relief '.Str::uuid(), 'description' => null]);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Paracetamol Forte '.Str::uuid(),
            'barcode' => 'P3-'.Str::uuid(),
            'description' => null,
            'sale_price' => 5000,
            'minimum_stock' => 2,
            'is_active' => true,
        ]);
        $supplier = Supplier::create([
            'name' => 'MediSupply '.Str::uuid(),
            'phone' => '0000000000',
            'email' => null,
            'address' => null,
            'notes' => null,
        ]);
        $staff = $this->makeUser('Rana Ahmad '.Str::uuid(), User::ROLE_PHARMACIST);

        $service = app(AiDatabaseAssistantService::class);

        $productResult = $service->ask('product: "'.str_replace('Paracetamol', 'Paracetmol', $product->name).'"');
        $this->assertSame('product_lookup', $productResult['intent']);
        $this->assertSame($product->name, $productResult['rows'][0]['product'] ?? null);

        $supplierResult = $service->ask('supplier: "'.str_replace('MediSupply', 'MediSuply', $supplier->name).'"');
        $this->assertSame('supplier_lookup', $supplierResult['intent']);
        $this->assertSame($supplier->name, $supplierResult['rows'][0]['supplier'] ?? null);

        $staffResult = $service->ask('staff: "'.str_replace('Ahmad', 'Ahmd', $staff->name).'"');
        $this->assertSame('staff_lookup', $staffResult['intent']);
        $this->assertSame($staff->name, $staffResult['rows'][0]['staff'] ?? null);

        $categoryResult = $service->ask('category: "'.str_replace('Relief', 'Relif', $category->name).'"');
        $this->assertSame('category_lookup', $categoryResult['intent']);
        $this->assertSame($category->name, $categoryResult['rows'][0]['category'] ?? null);
    }

    public function test_entity_picker_searches_real_records_and_inserts_server_resolved_exact_name(): void
    {
        $admin = $this->makeUser('Picker Admin '.Str::uuid(), User::ROLE_ADMIN);
        $this->actingAs($admin);

        $category = Category::create(['name' => 'Picker Category '.Str::uuid(), 'description' => null]);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Picker Medicine '.Str::uuid(),
            'barcode' => 'PICK-'.Str::uuid(),
            'description' => null,
            'sale_price' => 2500,
            'minimum_stock' => 1,
            'is_active' => true,
        ]);

        Livewire::test(AiDatabaseAssistant::class)
            ->assertSet('entityPickerOpen', false)
            ->assertDontSee($product->name)
            ->call('toggleEntityPicker')
            ->assertSet('entityPickerOpen', true)
            ->set('entityType', 'product')
            ->set('entitySearch', 'Picker Medicine')
            ->assertSee($product->name)
            ->call('insertEntity', 'product', $product->id)
            ->assertSet('question', 'product: "'.$product->name.'"')
            ->assertSet('entityPickerOpen', false);
    }

    public function test_admin_dashboard_exposes_domain_shortcuts_and_compact_ai_entity_picker(): void
    {
        $this->actingAs($this->makeUser('Dashboard Admin '.Str::uuid(), User::ROLE_ADMIN));

        $this->get('/admin')
            ->assertOk()
            ->assertSee('Admin shortcuts')
            ->assertSee('AI Assistant');

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
            'password' => Hash::make('password'),
            'phone' => '0000000000',
            'role' => $role,
            'is_active' => true,
        ]);
    }
}
