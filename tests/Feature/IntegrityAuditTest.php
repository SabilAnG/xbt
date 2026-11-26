<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\Formula;
use App\Models\Item;
use App\Models\Supplier;
use App\Models\User;
use App\Models\VendorService;
use App\Models\Wallet;
use App\Models\Worker;
use App\Models\WorkerPayroll;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Titik sambung antar modul yang rawan: hapus/ganti nama data yang dipakai modul lain. */
class IntegrityAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->actingAs(User::factory()->create());
    }

    public function test_receipt_form_without_a_purchase_goes_back_to_the_list(): void
    {
        $this->get(route('admin.receipts.create'))->assertRedirect(route('admin.receipts.index'));
    }

    public function test_supplier_with_vendor_services_cannot_be_deleted_and_rename_reaches_formulas(): void
    {
        $this->post(route('admin.vendor-services.store'), ['vendor' => 'Bengkel Joko', 'process' => 'Bending', 'rate' => 3_000])->assertSessionHasNoErrors();
        $supplier = Supplier::firstOrFail();
        $service = VendorService::firstOrFail();

        $this->post(route('admin.formulas.store'), [
            'motor_category' => 'Matic', 'motor_type' => 'Mio', 'name' => 'Mio', 'is_active' => 1,
            'parts' => [['name' => 'Leheran', 'components' => [['name' => 'P1', 'qty' => 1, 'work_mode' => 'vendor', 'vendor_service_id' => $service->id, 'materials' => []]]]],
        ])->assertSessionHasNoErrors();

        // Hapus supplier yang masih punya jasa: ditolak dengan pesan, bukan error 500.
        $this->delete(route('admin.suppliers.destroy', $supplier))->assertSessionHas('error');
        $this->assertModelExists($supplier);

        // Ganti nama supplier: nama vendor di komponen formula ikut berubah (tagihan berikutnya ke supplier yang sama).
        $this->put(route('admin.suppliers.update', $supplier), ['name' => 'CV Joko Bending', 'is_active' => 1])->assertSessionHasNoErrors();
        $this->assertSame('CV Joko Bending', Formula::firstOrFail()->parts()->first()->components()->first()->vendor);
        $this->assertSame(1, Supplier::count());
    }

    public function test_payroll_expenses_are_managed_from_the_payroll_slip_only(): void
    {
        $this->post(route('admin.wallets.store'), ['name' => 'Kas', 'type' => 'cash', 'opening_balance' => 1_000_000, 'is_active' => 1]);
        $kas = Wallet::firstOrFail();
        $this->post(route('admin.workers.store'), ['name' => 'Sidik', 'daily_wage' => 100_000, 'is_active' => 1]);
        $worker = Worker::firstOrFail();
        $this->post(route('admin.attendances.store'), ['worked_on' => '2026-12-01', 'rows' => [$worker->id => ['status' => 'hadir']]]);
        $this->post(route('admin.payrolls.store'), ['worker_id' => $worker->id, 'period_start' => '2026-11-30', 'period_end' => '2026-12-06']);
        $slip = WorkerPayroll::firstOrFail();
        $this->post(route('admin.payrolls.pay', $slip), ['paid_at' => '2026-12-06', 'wallet_id' => $kas->id])->assertSessionHasNoErrors();
        $expense = Expense::firstOrFail();

        // Menghapus/mengubah dari menu Pengeluaran ditolak supaya slip & dompet tetap cocok.
        $this->delete(route('admin.expenses.destroy', $expense))->assertSessionHas('error');
        $this->assertModelExists($expense);
        $this->get(route('admin.expenses.edit', $expense))->assertRedirect();
        $this->assertTrue($slip->fresh()->isPaid());
        $this->assertEquals(900_000, (float) $kas->fresh()->current_balance);
    }

    public function test_selling_more_than_stock_is_refused(): void
    {
        $item = Item::create(['sku' => 'KNL-1', 'name' => 'Knalpot', 'stock' => 2, 'is_active' => true]);

        $this->post(route('admin.sales.store'), ['sold_at' => '2026-12-01', 'item_id' => $item->id, 'qty' => 5, 'unit_price' => 100_000])
            ->assertSessionHasErrors('lines');
        $this->assertEquals(2, (float) $item->fresh()->stock);
    }
}
