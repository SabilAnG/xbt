<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\Item;
use App\Models\MaterialPurchase;
use App\Models\MaterialStock;
use App\Models\Production;
use App\Models\PurchasePayment;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\SupplierDeposit;
use App\Models\Wallet;
use App\Models\Warehouse;
use App\Models\WorkerAdvance;
use App\Services\DashboardSummary;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Laporan keuangan sederhana: laba rugi periode, arus kas, dan posisi
 * (kas, piutang, hutang, persediaan per gudang).
 *
 * Pembelian bahan dicatat sebagai persediaan, bukan biaya. Biayanya baru
 * diakui sebagai HPP ketika knalpot yang memakainya terjual.
 */
class FinanceReportController extends Controller
{
    public function __invoke(Request $request): View
    {
        $from = Carbon::parse($request->input('dari', now()->startOfMonth()->toDateString()))->startOfDay();
        $to = Carbon::parse($request->input('sampai', now()->endOfMonth()->toDateString()))->endOfDay();

        $sales = Sale::query()->posted()->whereBetween('sold_at', [$from->toDateString(), $to->toDateString()])->get();
        $expenses = Expense::query()->posted()->with('category')->whereBetween('spent_at', [$from->toDateString(), $to->toDateString()])->get();
        $productions = Production::query()->posted()->whereBetween('produced_at', [$from->toDateString(), $to->toDateString()])->get();
        $purchases = MaterialPurchase::query()->whereBetween('purchased_at', [$from->toDateString(), $to->toDateString()])->get();
        $payments = (float) PurchasePayment::query()->whereNull('supplier_deposit_id')->whereBetween('paid_at', [$from->toDateString(), $to->toDateString()])->sum('amount')
            + (float) SupplierDeposit::query()->whereBetween('paid_at', [$from->toDateString(), $to->toDateString()])->sum('amount');
        $received = SalePayment::query()->whereBetween('paid_at', [$from->toDateString(), $to->toDateString()])->sum('amount');
        // Uang keluar untuk biaya: kasbon dihitung saat diberikan, bukan saat dipotong di gaji.
        $expenseCash = (float) DashboardSummary::cashExpenses()->whereBetween('spent_at', [$from->toDateString(), $to->toDateString()])->sum('amount')
            + (float) WorkerAdvance::query()->whereBetween('given_at', [$from->toDateString(), $to->toDateString()])->sum('amount');

        $revenue = (float) $sales->sum('total');
        $cogs = (float) $sales->sum('total_cost');
        $operating = (float) $expenses->sum('amount');

        $inventory = collect(array_keys(Warehouse::TYPES))->mapWithKeys(function (string $type) {
            $warehouse = Warehouse::forType($type);
            $value = match ($type) {
                Warehouse::FINISHED => (float) Item::query()->where('stock', '>', 0)->get()->sum(fn (Item $item) => $item->stockValue()),
                Warehouse::VENDOR => (float) Production::query()->posted()->where('mode', Production::VENDOR)->get()->sum(fn (Production $job) => $job->outstandingValue()),
                default => (float) MaterialStock::query()->where('warehouse_id', $warehouse->id)->sum('value'),
            };

            return [$warehouse->name => $value];
        });

        return view('admin.finance.index', [
            'from' => $from,
            'to' => $to,
            'profit' => [
                'revenue' => $revenue,
                'cogs' => $cogs,
                'gross' => $revenue - $cogs,
                'operating' => $operating,
                'net' => $revenue - $cogs - $operating,
                'by_category' => $expenses->groupBy(fn (Expense $expense) => $expense->category?->name ?? 'Tanpa kategori')
                    ->map(fn ($rows) => (float) $rows->sum('amount'))->sortDesc(),
            ],
            'cash' => [
                'sales_in' => (float) $received,
                'purchase_out' => (float) $payments,
                'expense_out' => $expenseCash,
            ],
            'production' => [
                'count' => $productions->count(),
                'units' => (float) $productions->sum('qty'),
                'cost' => (float) $productions->sum('total_cost'),
                'waste' => (float) $productions->sum('waste_cost'),
                'purchases' => (float) $purchases->sum('total'),
            ],
            'position' => [
                'wallets' => Wallet::query()->where('is_active', true)->orderBy('sort_order')->get(),
                'receivable' => (float) Sale::query()->posted()->get()->sum(fn (Sale $sale) => $sale->receivable()),
                'payable' => (float) MaterialPurchase::query()->unpaid()->get()->sum(fn (MaterialPurchase $purchase) => $purchase->payable()),
                'inventory' => $inventory,
            ],
        ]);
    }
}
