<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\Sale;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WalletTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->actingAs(User::factory()->create());
    }

    private function wallet(string $name, float $opening = 0): Wallet
    {
        $this->post(route('admin.wallets.store'), ['name' => $name, 'type' => 'bank', 'opening_balance' => $opening, 'is_active' => 1])
            ->assertRedirect(route('admin.wallets.index'));

        return Wallet::where('name', $name)->firstOrFail();
    }

    public function test_sale_income_and_expense_move_the_chosen_wallet_balance(): void
    {
        $bca = $this->wallet('BCA Usaha', 1_000_000);
        $item = Item::create(['sku' => 'KNL-1', 'name' => 'Mio Racing', 'cost_price' => 400_000, 'opening_stock' => 2]);
        $item->recalculateStock();

        $this->post(route('admin.sales.store'), [
            'sold_at' => '2026-11-20', 'item_id' => $item->id, 'qty' => 1, 'unit_price' => 750_000,
            'paid_amount' => 500_000, 'wallet_id' => $bca->id,
        ])->assertRedirect();

        $this->assertSame(1_500_000.0, (float) $bca->refresh()->current_balance);

        // Pelunasan dicicil: tiap pembayaran masuk dompet, tidak boleh melebihi piutang.
        $sale = Sale::latest('id')->firstOrFail();
        $this->post(route('admin.sales.payments.store', $sale), ['paid_at' => '2026-11-22', 'amount' => 300_000, 'wallet_id' => $bca->id])->assertSessionHas('error');
        $this->post(route('admin.sales.payments.store', $sale), ['paid_at' => '2026-11-22', 'amount' => 250_000, 'wallet_id' => $bca->id])->assertSessionHasNoErrors();
        $this->assertSame(1_750_000.0, (float) $bca->refresh()->current_balance);
        $this->assertTrue($sale->fresh()->isPaidOff());
        $this->assertSame(2, $sale->payments()->count());

        $this->post(route('admin.expenses.store'), [
            'spent_at' => '2026-11-21', 'category' => 'Operasional',
            'description' => 'Beli gas', 'amount' => 250_000, 'wallet_id' => $bca->id,
        ])->assertRedirect(route('admin.expenses.index'));

        $this->assertSame(1_500_000.0, (float) $bca->refresh()->current_balance);
        $this->assertDatabaseHas('expense_categories', ['name' => 'Operasional']);
        $this->get(route('admin.wallets.show', $bca))->assertOk()->assertSee('Beli gas')->assertSee('Penjualan');

        $this->delete(route('admin.sales.destroy', $sale))->assertRedirect();
        $this->assertSame(750_000.0, (float) $bca->refresh()->current_balance);
    }

    public function test_transfer_moves_money_between_wallets_and_can_be_undone(): void
    {
        $bca = $this->wallet('BCA Usaha', 1_000_000);
        $kas = $this->wallet('Kas Bengkel');

        $this->post(route('admin.wallets.transfer.store'), [
            'from_wallet_id' => $bca->id, 'to_wallet_id' => $kas->id, 'amount' => 300_000, 'transferred_at' => '2026-11-22',
        ])->assertRedirect(route('admin.wallets.index'));

        $this->assertSame(700_000.0, (float) $bca->refresh()->current_balance);
        $this->assertSame(300_000.0, (float) $kas->refresh()->current_balance);

        $this->post(route('admin.wallets.transfer.store'), [
            'from_wallet_id' => $bca->id, 'to_wallet_id' => $bca->id, 'amount' => 1, 'transferred_at' => '2026-11-22',
        ])->assertSessionHasErrors('to_wallet_id');

        $transferId = $kas->transactions()->value('source_id');
        $this->delete(route('admin.wallets.transfers.destroy', $transferId))->assertRedirect();

        $this->assertSame(1_000_000.0, (float) $bca->refresh()->current_balance);
        $this->assertSame(0.0, (float) $kas->refresh()->current_balance);
    }

    public function test_wallet_pages_render_and_wallet_with_mutations_cannot_be_deleted(): void
    {
        $bca = $this->wallet('BCA Usaha', 100_000);

        $this->post(route('admin.wallets.manual.store'), [
            'wallet_id' => $bca->id, 'direction' => 'in', 'amount' => 50_000, 'occurred_at' => '2026-11-22', 'description' => 'Setor modal',
        ])->assertRedirect(route('admin.wallets.show', $bca));

        $this->assertSame(150_000.0, (float) $bca->refresh()->current_balance);

        foreach (['admin.wallets.index', 'admin.wallets.create', 'admin.wallets.transfer', 'admin.wallets.manual', 'admin.sales.create', 'admin.expenses.create', 'admin.purchases.create'] as $route) {
            $this->get(route($route))->assertOk();
        }

        $this->get(route('admin.sales.create'))->assertSee('BCA Usaha');

        $this->delete(route('admin.wallets.destroy', $bca))->assertSessionHas('error');
        $this->assertModelExists($bca);
    }
}
