<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\ExpenseType;
use App\Models\Worker;
use App\Models\WorkerAdvance;
use App\Models\WorkerPayroll;
use App\Models\WorkerPieceEarning;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Penggajian mingguan pekerja harian:
 * upah = hari hadir × upah harian (½ hari dihitung setengah),
 * uang makan = hari masuk × uang makan, lembur = jam × tarif lembur,
 * dikurangi kasbon. Dibayar dari dompet dan tercatat di Pengeluaran.
 */
class PayrollService
{
    public function __construct(private WalletService $wallets) {}

    /**
     * Rekap absensi yang belum digaji dalam satu periode.
     *
     * @return array{worker: Worker, attendances: Collection<int, Attendance>, days_full: float, days_half: float, overtime_hours: float, wage_total: float, meal_total: float, overtime_total: float, pieces: Collection<int, WorkerPieceEarning>, piece_total: float, gross_pay: float, advance_balance: float, advance_deduction: float, net_pay: float}
     */
    public function summary(Worker $worker, string $start, string $end, ?float $deduction = null): array
    {
        $attendances = $worker->attendances()->whereBetween('worked_on', [$start, $end])->whereNull('worker_payroll_id')->get();
        $full = (float) $attendances->where('status', 'hadir')->count();
        $half = (float) $attendances->where('status', 'setengah')->count();
        $mealDays = (float) $attendances->filter(fn (Attendance $row) => Attendance::STATUSES[$row->status][2] ?? false)->count();
        $overtime = (float) $attendances->sum('overtime_hours');

        $wage = ($full + $half * 0.5) * (float) $worker->daily_wage;
        $meal = $mealDays * (float) $worker->meal_allowance;
        $overtimeTotal = $overtime * (float) $worker->overtime_rate;
        $pieces = WorkerPieceEarning::query()->where('worker_id', $worker->id)->whereNull('worker_payroll_id')
            ->whereBetween('earned_on', [$start, $end])->get();
        $pieceTotal = (float) $pieces->sum('amount');
        $gross = $wage + $meal + $overtimeTotal + $pieceTotal;
        $balance = $worker->advanceBalance();
        $deduction = min(max($deduction ?? $balance, 0), $balance, $gross);

        return [
            'worker' => $worker,
            'attendances' => $attendances,
            'days_full' => $full,
            'days_half' => $half,
            'overtime_hours' => $overtime,
            'wage_total' => $wage,
            'meal_total' => $meal,
            'overtime_total' => $overtimeTotal,
            'pieces' => $pieces,
            'piece_total' => $pieceTotal,
            'gross_pay' => $gross,
            'advance_balance' => $balance,
            'advance_deduction' => $deduction,
            'net_pay' => $gross - $deduction,
        ];
    }

    /** Buat slip gaji (belum dibayar) dan kunci absensinya. */
    public function create(Worker $worker, string $start, string $end, ?float $deduction = null, ?string $notes = null): WorkerPayroll
    {
        return DB::transaction(function () use ($worker, $start, $end, $deduction, $notes) {
            $summary = $this->summary($worker, $start, $end, $deduction);

            if ($summary['attendances']->isEmpty() && $summary['pieces']->isEmpty()) {
                throw new RuntimeException("Tidak ada absensi atau upah borongan {$worker->name} yang belum digaji pada periode ini.");
            }

            $payroll = WorkerPayroll::create([
                'payroll_number' => DocumentNumber::next('worker_payrolls', Carbon::parse($end)),
                'worker_id' => $worker->id,
                'period_start' => $start,
                'period_end' => $end,
                'days_full' => $summary['days_full'],
                'days_half' => $summary['days_half'],
                'overtime_hours' => $summary['overtime_hours'],
                'daily_wage' => $worker->daily_wage,
                'meal_allowance' => $worker->meal_allowance,
                'overtime_rate' => $worker->overtime_rate,
                'wage_total' => $summary['wage_total'],
                'meal_total' => $summary['meal_total'],
                'overtime_total' => $summary['overtime_total'],
                'piece_total' => $summary['piece_total'],
                'gross_pay' => $summary['gross_pay'],
                'advance_deduction' => $summary['advance_deduction'],
                'net_pay' => $summary['net_pay'],
                'status' => WorkerPayroll::DRAFT,
                'notes' => $notes,
            ]);

            Attendance::whereIn('id', $summary['attendances']->pluck('id'))->update(['worker_payroll_id' => $payroll->id]);
            WorkerPieceEarning::whereIn('id', $summary['pieces']->pluck('id'))->update(['worker_payroll_id' => $payroll->id]);

            return $payroll;
        });
    }

    /** Bayar slip: uang keluar dari dompet; gaji kotor tercatat sebagai pengeluaran. */
    public function pay(WorkerPayroll $payroll, int $walletId, string $date): void
    {
        if ($payroll->isPaid()) {
            throw new RuntimeException('Slip gaji ini sudah dibayar.');
        }

        DB::transaction(function () use ($payroll, $walletId, $date) {
            $payroll->loadMissing('worker');
            $category = $this->category();
            $label = 'Gaji '.$payroll->worker->name.' '.$payroll->periodLabel().' · '.$payroll->payroll_number;

            // Yang dibayar tunai dari dompet.
            $expense = Expense::create([
                'reference_number' => DocumentNumber::next('expenses', Carbon::parse($date)),
                'spent_at' => $date, 'expense_category_id' => $category->id, 'wallet_id' => $walletId,
                'amount' => $payroll->net_pay, 'description' => $label, 'paid_to' => $payroll->worker->name,
                'data_status' => null, 'status' => 'posted', 'posted_at' => now(),
            ]);
            $this->wallets->sync($expense, $walletId, 'out', (float) $payroll->net_pay, $date, $category->name.': '.$label);

            // Bagian yang dibayar dengan memotong kasbon (uangnya sudah keluar saat kasbon).
            $deductionExpense = null;
            if ((float) $payroll->advance_deduction > 0) {
                $deductionExpense = Expense::create([
                    'reference_number' => DocumentNumber::next('expenses', Carbon::parse($date)),
                    'spent_at' => $date, 'expense_category_id' => $category->id, 'wallet_id' => null,
                    'amount' => $payroll->advance_deduction, 'description' => $label.' · potong kasbon', 'paid_to' => $payroll->worker->name,
                    'data_status' => null, 'status' => 'posted', 'posted_at' => now(),
                ]);
            }

            $payroll->forceFill([
                'status' => WorkerPayroll::PAID, 'paid_at' => $date, 'wallet_id' => $walletId,
                'expense_id' => $expense->id, 'deduction_expense_id' => $deductionExpense?->id,
            ])->save();
        });
    }

    /** Batalkan pembayaran: pengeluaran & mutasi dompetnya dihapus, slip kembali belum dibayar. */
    public function unpay(WorkerPayroll $payroll): void
    {
        DB::transaction(function () use ($payroll) {
            foreach (Expense::whereIn('id', array_filter([$payroll->expense_id, $payroll->deduction_expense_id]))->get() as $expense) {
                $this->wallets->forget($expense);
                $expense->delete();
            }

            $payroll->forceFill(['status' => WorkerPayroll::DRAFT, 'paid_at' => null, 'wallet_id' => null, 'expense_id' => null, 'deduction_expense_id' => null])->save();
        });
    }

    /** Hapus slip yang belum dibayar; absensinya bisa digaji ulang. */
    public function delete(WorkerPayroll $payroll): void
    {
        if ($payroll->isPaid()) {
            throw new RuntimeException('Batalkan pembayarannya dulu sebelum menghapus slip.');
        }

        DB::transaction(function () use ($payroll) {
            $payroll->attendances()->update(['worker_payroll_id' => null]);
            WorkerPieceEarning::where('worker_payroll_id', $payroll->id)->update(['worker_payroll_id' => null]);
            $payroll->delete();
        });
    }

    public function giveAdvance(Worker $worker, float $amount, ?int $walletId, string $date, ?string $notes = null): WorkerAdvance
    {
        if ($amount <= 0) {
            throw new RuntimeException('Nominal kasbon harus lebih dari 0.');
        }

        return DB::transaction(function () use ($worker, $amount, $walletId, $date, $notes) {
            $advance = $worker->advances()->create(['given_at' => $date, 'amount' => $amount, 'wallet_id' => $walletId, 'notes' => $notes]);
            $this->wallets->sync($advance, $walletId, 'out', $amount, $date, 'Kasbon '.$worker->name.($notes ? ' · '.$notes : ''));

            return $advance;
        });
    }

    public function deleteAdvance(WorkerAdvance $advance): void
    {
        $worker = $advance->worker;

        if ($worker->advanceBalance() + 0.01 < (float) $advance->amount) {
            throw new RuntimeException('Kasbon ini sudah dipotong di slip gaji, jadi tidak bisa dihapus.');
        }

        DB::transaction(function () use ($advance) {
            $this->wallets->forget($advance);
            $advance->delete();
        });
    }

    private function category(): ExpenseCategory
    {
        $type = ExpenseType::firstOrCreate(['slug' => 'usaha-knalpot'], ['name' => 'Usaha Knalpot']);

        return ExpenseCategory::query()->where('name', 'Gaji pekerja')->first()
            ?? ExpenseCategory::create(['name' => 'Gaji pekerja', 'slug' => $this->slug(), 'expense_type_id' => $type->id, 'is_active' => true]);
    }

    private function slug(): string
    {
        $slug = Str::slug('Gaji pekerja');

        for ($i = 2, $base = $slug; ExpenseCategory::where('slug', $slug)->exists(); $i++) {
            $slug = $base.'-'.$i;
        }

        return $slug;
    }
}
