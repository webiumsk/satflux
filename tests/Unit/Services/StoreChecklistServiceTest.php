<?php

namespace Tests\Unit\Services;

use App\Models\Store;
use App\Models\StoreChecklist;
use App\Services\StoreChecklistService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreChecklistServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_checklist_items_for_blink_returns_expected_keys(): void
    {
        $items = StoreChecklistService::getChecklistItems('blink');

        $this->assertIsArray($items);
        $this->assertArrayHasKey('connect_wallet', $items);
        $this->assertArrayHasKey('enable_lightning', $items);
        $this->assertArrayHasKey('test_invoice', $items);

        $this->assertSame('connect_wallet', $items['connect_wallet']['key']);
        $this->assertStringContainsString('Blink', $items['connect_wallet']['description']);
        $this->assertArrayHasKey('order', $items['connect_wallet']);
    }

    public function test_get_checklist_items_for_aqua_boltz_returns_expected_keys(): void
    {
        $items = StoreChecklistService::getChecklistItems('aqua_boltz');

        $this->assertIsArray($items);
        $this->assertArrayHasKey('configure_wallet', $items);
        $this->assertArrayHasKey('enable_boltz_plugin', $items);
        $this->assertArrayHasKey('connect_aqua_wallet', $items);
        $this->assertArrayHasKey('verify_swap_routing', $items);
        $this->assertArrayHasKey('test_lightning_invoice', $items);

        $this->assertSame('configure_wallet', $items['configure_wallet']['key']);
        $this->assertSame(5, count($items));
    }

    public function test_get_checklist_items_for_unknown_type_returns_empty_array(): void
    {
        $items = StoreChecklistService::getChecklistItems('unknown_type');

        $this->assertIsArray($items);
        $this->assertEmpty($items);
    }

    public function test_get_all_checklist_items_has_blink_aqua_and_cashu(): void
    {
        $all = StoreChecklistService::getAllChecklistItems();

        $this->assertIsArray($all);
        $this->assertArrayHasKey('blink', $all);
        $this->assertArrayHasKey('aqua_boltz', $all);
        $this->assertArrayHasKey('cashu', $all);
        $this->assertIsArray($all['blink']);
        $this->assertIsArray($all['aqua_boltz']);
        $this->assertIsArray($all['cashu']);
    }

    public function test_get_checklist_items_for_cashu_returns_three_keys(): void
    {
        $items = StoreChecklistService::getChecklistItems('cashu');

        $this->assertCount(3, $items);
        $this->assertArrayHasKey('verify_mint_ln', $items);
        $this->assertArrayHasKey('confirm_cashu_enabled', $items);
        $this->assertArrayHasKey('test_cashu_payment', $items);
    }

    public function test_ensure_checklist_initialized_creates_only_missing_rows(): void
    {
        $store = Store::factory()->create(['wallet_type' => 'blink']);
        StoreChecklistService::ensureChecklistInitialized($store);
        $this->assertSame(3, StoreChecklist::where('store_id', $store->id)->count());

        StoreChecklistService::ensureChecklistInitialized($store);
        $this->assertSame(3, StoreChecklist::where('store_id', $store->id)->count());
    }

    public function test_initialize_checklist_creates_records_for_store(): void
    {
        $store = Store::factory()->create(['wallet_type' => 'blink']);

        StoreChecklistService::initializeChecklist($store->id, 'blink');

        $count = StoreChecklist::where('store_id', $store->id)->count();
        $this->assertSame(3, $count);

        $keys = StoreChecklist::where('store_id', $store->id)->pluck('item_key')->sort()->values()->toArray();
        $this->assertSame(['connect_wallet', 'enable_lightning', 'test_invoice'], $keys);
    }

    public function test_initialize_checklist_for_aqua_boltz_creates_five_items(): void
    {
        $store = Store::factory()->create(['wallet_type' => 'aqua_boltz']);

        StoreChecklistService::initializeChecklist($store->id, 'aqua_boltz');

        $count = StoreChecklist::where('store_id', $store->id)->count();
        $this->assertSame(5, $count);
    }

    public function test_initialize_checklist_for_unknown_type_creates_nothing(): void
    {
        $store = Store::factory()->create();

        StoreChecklistService::initializeChecklist($store->id, 'unknown');

        $count = StoreChecklist::where('store_id', $store->id)->count();
        $this->assertSame(0, $count);
    }
}
