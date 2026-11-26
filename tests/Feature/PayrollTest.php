<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Expense;
use App\Models\User;
use App\Models\Wallet;
use App\Models\Worker;
use App\Models\WorkerPayroll;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Pekerja harian: absensi → slip gaji mingguan (upah + makan + lembur − kasbon) → dibayar dari dompet. */
class PayrollTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->actingAs(User::factory()->create());
    }

    public function test_weekly_payroll_from_attendance_overtime_meals_and_advances(): void
    {
        $this->post(route('admin.wallets.store'), ['name' => 'Kas', 'type' => 'cash', 'opening_balance' => 2_000_000, 'is_active' => 1]);
        $kas = Wallet::where('name', 'Kas')->firstOrFail();

        $this->post(route('admin.workers.store'), [
            'name' => 'Budi Santoso', 'position' => 'Tukang las', 'daily_wage' => 120_000, 'meal_allowance' => 15_000, 'overtime_rate' => 20_000, 'is_active' => 1,
        ])->assertSessionHasNoErrors();
        $budi = Worker::firstOrFail();
        $this->get(route('admin.workers.index'))->assertOk()->assertSee('Budi Santoso');

        // Senin–Sabtu 1–6 Des 2026: 4 hadir (1 dengan lembur 3 jam), 1 setengah hari, 1 alpa.
        $week = ['2026-11-30' => 'hadir', '2026-12-01' => 'hadir', '2026-12-02' => 'setengah', '2026-12-03' => 'hadir', '2026-12-04' => 'alpa', '2026-12-05' => 'hadir'];
        foreach ($week as $date => $status) {
            $this->post(route('admin.attendances.store'), [
                'worked_on' => $date,
                'rows' => [$budi->id => ['status' => $status, 'overtime_hours' => $date === '2026-12-01' ? 3 : 0]],
            ])->assertSessionHasNoErrors();
        }
        $this->get(route('admin.attendances.index', ['tanggal' => '2026-12-01']))->assertOk()->assertSee('Budi Santoso');

        // Kasbon 100.000 dari Kas.
        $this->post(route('admin.workers.advances.store', $budi), ['given_at' => '2026-12-02', 'amount' => 100_000, 'wallet_id' => $kas->id])->assertSessionHasNoErrors();
        $this->assertEquals(1_900_000, (float) $kas->fresh()->current_balance);

        // Slip: 4,5 hari × 120.000 = 540.000; makan 5 × 15.000 = 75.000; lembur 3 × 20.000 = 60.000 → 675.000 − kasbon 100.000.
        $this->get(route('admin.payrolls.index', ['minggu' => '2026-12-01']))->assertOk()->assertSee('Budi Santoso');
        $this->post(route('admin.payrolls.store'), ['worker_id' => $budi->id, 'period_start' => '2026-11-30', 'period_end' => '2026-12-06'])->assertSessionHasNoErrors();
        $payroll = WorkerPayroll::firstOrFail();
        $this->assertEquals(540_000, (float) $payroll->wage_total);
        $this->assertEquals(75_000, (float) $payroll->meal_total);
        $this->assertEquals(60_000, (float) $payroll->overtime_total);
        $this->assertEquals(675_000, (float) $payroll->gross_pay);
        $this->assertEquals(100_000, (float) $payroll->advance_deduction);
        $this->assertEquals(575_000, (float) $payroll->net_pay);
        $this->assertEquals(0, $budi->fresh()->advanceBalance());

        // Absensi yang sudah masuk slip terkunci.
        $this->post(route('admin.attendances.store'), ['worked_on' => '2026-12-04', 'rows' => [$budi->id => ['status' => 'hadir']]]);
        $this->assertSame('alpa', Attendance::whereDate('worked_on', '2026-12-04')->value('status'));

        // Bayar dari Kas: dompet berkurang 575.000; pengeluaran gaji = 675.000 (575.000 tunai + 100.000 potong kasbon).
        $this->get(route('admin.payrolls.show', $payroll))->assertOk()->assertSee('575.000');
        $this->post(route('admin.payrolls.pay', $payroll), ['paid_at' => '2026-12-06', 'wallet_id' => $kas->id])->assertSessionHasNoErrors();
        $this->assertTrue($payroll->fresh()->isPaid());
        $this->assertEquals(1_325_000, (float) $kas->fresh()->current_balance);
        $this->assertEquals(675_000, (float) Expense::query()->posted()->sum('amount'));

        // Batal bayar → dompet & pengeluaran kembali; slip bisa dihapus lalu absensi terbuka lagi.
        $this->post(route('admin.payrolls.unpay', $payroll))->assertSessionHasNoErrors();
        $this->assertEquals(1_900_000, (float) $kas->fresh()->current_balance);
        $this->assertSame(0, Expense::count());
        $this->delete(route('admin.payrolls.destroy', $payroll))->assertSessionHasNoErrors();
        $this->assertSame(0, Attendance::whereNotNull('worker_payroll_id')->count());
        $this->assertEquals(100_000, $budi->fresh()->advanceBalance());
    }
}
