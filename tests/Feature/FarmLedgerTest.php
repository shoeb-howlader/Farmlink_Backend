<?php

namespace Tests\Feature;

use App\Models\Farm;
use App\Models\FarmCycle;
use App\Models\FarmLedgerEntry;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FarmLedgerTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $deo;
    protected User $farmer;
    protected User $otherFarmer;
    protected Farm $farm;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'data_entry_operator', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'farmer', 'guard_name' => 'web']);

        $this->admin = User::factory()->create(['email' => 'admin@test.com', 'name' => 'Admin User']);
        $this->admin->assignRole('admin');

        $this->deo = User::factory()->create(['email' => 'deo@test.com', 'name' => 'DEO Officer']);
        $this->deo->assignRole('data_entry_operator');

        $this->farmer = User::factory()->create(['email' => 'farmer@test.com', 'name' => 'Karim Farmer']);
        $this->farmer->assignRole('farmer');

        $this->otherFarmer = User::factory()->create(['email' => 'other@test.com', 'name' => 'Other Farmer']);
        $this->otherFarmer->assignRole('farmer');

        $this->farm = Farm::factory()->create([
            'user_id' => $this->farmer->id,
            'farm_name' => 'Karim Shrimp Farm',
        ]);
    }

    public function test_farmer_can_create_cycle_and_manual_ledger_entries(): void
    {
        // 1. Create a cycle
        $cycleRes = $this->actingAs($this->farmer)
            ->postJson("/api/v1/farms/{$this->farm->id}/cycles", [
                'label' => 'Cycle 1 - Summer 2026',
                'start_date' => '2026-06-01',
            ]);

        $cycleRes->assertCreated()
            ->assertJsonPath('data.label', 'Cycle 1 - Summer 2026');

        $cycleId = $cycleRes->json('data.id');

        // 2. Add an expense entry
        $expenseRes = $this->actingAs($this->farmer)
            ->postJson("/api/v1/farms/{$this->farm->id}/ledger", [
                'entry_type' => 'expense',
                'category' => 'Feed',
                'amount' => 15000.50,
                'entry_date' => '2026-06-15',
                'cycle_id' => $cycleId,
                'note' => 'Purchased 10 bags of nursery feed',
            ]);

        $expenseRes->assertCreated()
            ->assertJsonPath('data.amount', 15000.5)
            ->assertJsonPath('data.category', 'Feed')
            ->assertJsonPath('data.entered_by_role', 'farmer');

        // 3. Add an income entry with receipt slip upload
        $slip = UploadedFile::fake()->image('harvest_slip.jpg');
        $incomeRes = $this->actingAs($this->farmer)
            ->postJson("/api/v1/farms/{$this->farm->id}/ledger", [
                'entry_type' => 'income',
                'category' => 'Harvest Sale',
                'amount' => 50000.00,
                'entry_date' => '2026-07-20',
                'cycle_id' => $cycleId,
                'note' => 'Sold 200kg tiger shrimp',
                'photo' => $slip,
            ]);

        $incomeRes->assertCreated()
            ->assertJsonPath('data.amount', 50000)
            ->assertJsonPath('data.category', 'Harvest Sale');

        $this->assertNotNull($incomeRes->json('data.photo_url'));

        // 4. Check ledger index and summaries
        $indexRes = $this->actingAs($this->farmer)
            ->getJson("/api/v1/farms/{$this->farm->id}/ledger");

        $indexRes->assertOk()
            ->assertJsonPath('data.summary.lifetime.total_income', 50000)
            ->assertJsonPath('data.summary.lifetime.total_expense', 15000.5)
            ->assertJsonPath('data.summary.lifetime.net_profit', 34999.5)
            ->assertJsonPath('data.summary.current_cycle.total_income', 50000)
            ->assertJsonPath('data.summary.current_cycle.total_expense', 15000.5);
    }

    public function test_automatic_ledger_entry_is_created_when_order_is_placed(): void
    {
        $product = Product::factory()->create([
            'name' => 'Aquaculture Floating Feed 25kg',
            'price' => 2000.00,
            'stock' => 50,
        ]);

        $orderRes = $this->actingAs($this->farmer)
            ->postJson('/api/v1/orders', [
                'farm_id' => $this->farm->id,
                'items' => [
                    [
                        'product_id' => $product->id,
                        'quantity' => 3,
                    ],
                ],
            ]);

        $orderRes->assertCreated();
        $orderId = $orderRes->json('data.id');

        // Check that a system_order ledger entry was automatically created
        $ledgerEntry = FarmLedgerEntry::where('farm_id', $this->farm->id)
            ->where('source', 'system_order')
            ->where('source_reference_id', $orderId)
            ->first();

        $this->assertNotNull($ledgerEntry);
        $this->assertEquals(6000.00, (float) $ledgerEntry->amount);
        $this->assertEquals('expense', $ledgerEntry->entry_type);
        $this->assertEquals('Feed', $ledgerEntry->category);
        $this->assertEquals('farmer', $ledgerEntry->entered_by_role);

        // System generated entries cannot be edited or voided directly
        $updateRes = $this->actingAs($this->farmer)
            ->putJson("/api/v1/farms/{$this->farm->id}/ledger/{$ledgerEntry->id}", [
                'amount' => 1000.00,
            ]);
        $updateRes->assertForbidden();

        $voidRes = $this->actingAs($this->farmer)
            ->postJson("/api/v1/farms/{$this->farm->id}/ledger/{$ledgerEntry->id}/void", [
                'void_reason' => 'Mistake',
            ]);
        $voidRes->assertForbidden();
    }

    public function test_deo_can_create_entry_on_behalf_of_farmer_and_void_own_entry(): void
    {
        // DEO adds an expense
        $createRes = $this->actingAs($this->deo)
            ->postJson("/api/v1/farms/{$this->farm->id}/ledger", [
                'entry_type' => 'expense',
                'category' => 'Electricity',
                'amount' => 4500.00,
                'entry_date' => '2026-08-01',
                'note' => 'Assisted entry for commercial meter bill',
            ]);

        $createRes->assertCreated()
            ->assertJsonPath('data.entered_by_role', 'deo');

        $entryId = $createRes->json('data.id');

        // DEO cannot edit (append-only)
        $editRes = $this->actingAs($this->deo)
            ->putJson("/api/v1/farms/{$this->farm->id}/ledger/{$entryId}", [
                'amount' => 4000.00,
            ]);
        $editRes->assertForbidden();

        // DEO can void their own entry
        $voidRes = $this->actingAs($this->deo)
            ->postJson("/api/v1/farms/{$this->farm->id}/ledger/{$entryId}/void", [
                'void_reason' => 'Entered duplicate meter reading',
            ]);

        $voidRes->assertOk()
            ->assertJsonPath('data.is_voided', true)
            ->assertJsonPath('data.void_reason', 'Entered duplicate meter reading');

        // Voided entry amount is excluded from totals
        $indexRes = $this->actingAs($this->farmer)
            ->getJson("/api/v1/farms/{$this->farm->id}/ledger");
        $indexRes->assertOk()
            ->assertJsonPath('data.summary.lifetime.total_expense', 0);
    }

    public function test_admin_has_full_management_and_audit_logging(): void
    {
        $entry = FarmLedgerEntry::create([
            'farm_id' => $this->farm->id,
            'entry_type' => 'expense',
            'category' => 'Repair',
            'amount' => 8000.00,
            'entry_date' => '2026-08-10',
            'note' => 'Aerator motor rewinding',
            'source' => 'manual',
            'entered_by' => $this->farmer->id,
            'entered_by_role' => 'farmer',
        ]);

        // Admin updates the entry
        $updateRes = $this->actingAs($this->admin)
            ->putJson("/api/v1/farms/{$this->farm->id}/ledger/{$entry->id}", [
                'amount' => 7500.00,
                'note' => 'Corrected motor rewinding cost after review',
            ]);

        $updateRes->assertOk()
            ->assertJsonPath('data.amount', 7500);

        // Admin voids the entry
        $voidRes = $this->actingAs($this->admin)
            ->postJson("/api/v1/farms/{$this->farm->id}/ledger/{$entry->id}/void", [
                'void_reason' => 'Dispute resolution: expense refunded by contractor',
            ]);

        $voidRes->assertOk()
            ->assertJsonPath('data.is_voided', true);
    }

    public function test_other_farmer_cannot_access_or_tamper_with_ledger(): void
    {
        $res = $this->actingAs($this->otherFarmer)
            ->getJson("/api/v1/farms/{$this->farm->id}/ledger");

        $res->assertForbidden();

        $postRes = $this->actingAs($this->otherFarmer)
            ->postJson("/api/v1/farms/{$this->farm->id}/ledger", [
                'entry_type' => 'expense',
                'category' => 'Feed',
                'amount' => 1000,
                'entry_date' => '2026-08-01',
            ]);

        $postRes->assertForbidden();
    }
}
