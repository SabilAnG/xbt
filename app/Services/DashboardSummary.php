<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Item;
use App\Models\Material;
use App\Models\MaterialPurchase;
use App\Models\Production;
use App\Models\PurchasePayment;
use App\Models\Sale;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Angka untuk dashboard admin — padanan sheet "Ringkasan" di buku kerja,
 * rumus per rumus. Datanya kecil, jadi pengelompokan dilakukan di PHP supaya
 * sama persis di MySQL dan SQLite (dipakai test).
 */
class DashboardSummary
{
    /** Ringkasan per bulan selalu 12 baris, seperti blok "PER BULAN". */
    public const MONTHS = 12;

    /**
     * @return array{
     *     total_stock: float, stock_value: float, products_out: int, products_low: int,
     *     materials_alert: int, revenue: float, receivable: float, expenses: float,
     *     needs_detail: int, gross_profit: float, cash_in: float,
     *     purchase_payable: float, draft_productions: int
     * }
     */
    public function headline(): array
    {
        $items = Item::query()->where('is_active', true)->get(['stock', 'min_stock', 'cost_price']);
        $materials = Material::query()->where('is_active', true)->get(['stock', 'min_stock']);
        $sales = Sale::query()->posted()->get(['total', 'total_cost', 'paid_amount']);

        return [
            'total_stock' => (float) $items->sum('stock'),
            'stock_value' => (float) $items->sum(fn (Item $item) => $item->stockValue()),
            'products_out' => $items->filter(fn (Item $item) => $item->stockStatus() === 'HABIS')->count(),
            'products_low' => $items->filter(fn (Item $item) => $item->stockStatus() === 'MENIPIS')->count(),
            'materials_alert' => $materials->filter(fn (Material $material) => $material->stockStatus() !== 'AMAN')->count(),
            'revenue' => (float) $sales->sum('total'),
            'gross_profit' => (float) $sales->sum(fn (Sale $sale) => (float) $sale->total - (float) $sale->total_cost),
            'cash_in' => (float) $sales->sum('paid_amount'),
            'receivable' => (float) $sales->sum(fn (Sale $sale) => $sale->receivable()),
            'expenses' => (float) Expense::query()->posted()->sum('amount') + (float) PurchasePayment::query()->sum('amount'),
            'purchase_payable' => (float) MaterialPurchase::query()->unpaid()->get()->sum(fn (MaterialPurchase $row) => $row->payable()),
            'draft_productions' => Production::query()->where('status', Production::DRAFT)->count(),
            'needs_detail' => Expense::query()->where('data_status', 'PERLU DIRINCI')->count(),
        ];
    }

    /**
     * Rekap per bulan: 12 bulan mulai bulan transaksi pertama (diperpanjang bila
     * ada transaksi setelahnya).
     *
     * @return Collection<int, array{month: Carbon, produced: float, sold: float, revenue: float, gross_profit: float, cash_in: float, expenses: float, purchases: float, total_out: float, net_cash: float}>
     */
    public function monthly(): Collection
    {
        $productions = Production::query()->posted()->get(['produced_at', 'qty'])
            ->groupBy(fn (Production $row) => $row->produced_at->format('Y-m'));
        $sales = Sale::query()->posted()->with('items:id,sale_id,qty')->get()
            ->groupBy(fn (Sale $row) => $row->sold_at->format('Y-m'));
        $expenses = Expense::query()->posted()->get(['spent_at', 'amount'])
            ->groupBy(fn (Expense $row) => $row->spent_at->format('Y-m'));
        $purchases = PurchasePayment::query()->get(['paid_at', 'amount'])
            ->groupBy(fn (PurchasePayment $row) => $row->paid_at->format('Y-m'));

        $months = collect([...$productions->keys(), ...$sales->keys(), ...$expenses->keys(), ...$purchases->keys()]);
        $cursor = $months->isEmpty()
            ? now()->startOfMonth()
            : Carbon::createFromFormat('Y-m-d', $months->min().'-01')->startOfMonth();
        $end = $cursor->copy()->addMonths(self::MONTHS - 1);

        if ($months->isNotEmpty()) {
            $end = $end->max(Carbon::createFromFormat('Y-m-d', $months->max().'-01'));
        }

        $rows = collect();

        while ($cursor <= $end) {
            $key = $cursor->format('Y-m');
            $monthSales = $sales->get($key, collect());
            $cashIn = (float) $monthSales->sum('paid_amount');
            $expense = (float) $expenses->get($key, collect())->sum('amount');
            $purchase = (float) $purchases->get($key, collect())->sum('amount');
            $revenue = (float) $monthSales->sum('total');

            $rows->push([
                'month' => $cursor->copy(),
                'produced' => (float) $productions->get($key, collect())->sum('qty'),
                'sold' => (float) $monthSales->sum(fn (Sale $sale) => $sale->items->sum('qty')),
                'revenue' => $revenue,
                'gross_profit' => $revenue - (float) $monthSales->sum('total_cost'),
                'cash_in' => $cashIn,
                'expenses' => $expense,
                'purchases' => $purchase,
                'total_out' => $expense + $purchase,
                'net_cash' => $cashIn - $expense - $purchase,
            ]);

            $cursor->addMonth();
        }

        return $rows;
    }

    /**
     * Ringkasan per merek: jumlah produk, stok, nilai stok, diproduksi, terjual, omzet.
     *
     * @return Collection<int, array{name: string, color: string, products: int, stock: float, stock_value: float, produced: float, sold: float, revenue: float}>
     */
    public function byBrand(): Collection
    {
        $items = Item::query()->get(['id', 'brand', 'stock', 'cost_price'])->groupBy(fn (Item $item) => (string) $item->brand);
        $produced = Production::query()->posted()->join('items', 'items.id', '=', 'productions.item_id')
            ->selectRaw('items.brand AS brand, SUM(productions.qty) AS qty')->groupBy('items.brand')->pluck('qty', 'brand');
        $sales = DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')->where('sales.status', 'posted')
            ->join('items', 'items.id', '=', 'sale_items.item_id')
            ->selectRaw('items.brand AS brand, SUM(sale_items.qty) AS qty, SUM(sale_items.subtotal) AS revenue')
            ->groupBy('items.brand')->get()->keyBy('brand');

        return Brand::query()->ordered()->get()->map(function (Brand $brand) use ($items, $produced, $sales) {
            $rows = $items->get($brand->name, collect());

            return [
                'name' => $brand->name,
                'color' => $brand->color,
                'products' => $rows->count(),
                'stock' => (float) $rows->sum('stock'),
                'stock_value' => (float) $rows->sum(fn (Item $item) => $item->stockValue()),
                'produced' => (float) ($produced[$brand->name] ?? 0),
                'sold' => (float) ($sales->get($brand->name)->qty ?? 0),
                'revenue' => (float) ($sales->get($brand->name)->revenue ?? 0),
            ];
        });
    }

    /**
     * Pengeluaran per kategori, urut seperti sheet Daftar, termasuk yang nol.
     * Pembelian bahan tidak ikut: bahan adalah persediaan, biayanya masuk HPP.
     *
     * @return Collection<int, array{name: string, total: float}>
     */
    public function expensesByCategory(): Collection
    {
        $totals = Expense::query()->posted()
            ->selectRaw('expense_category_id, SUM(amount) AS total')
            ->groupBy('expense_category_id')
            ->pluck('total', 'expense_category_id');

        return ExpenseCategory::query()->orderBy('id')->get()
            ->map(fn (ExpenseCategory $category) => [
                'name' => $category->name,
                'total' => (float) ($totals[$category->id] ?? 0),
            ]);
    }
}
