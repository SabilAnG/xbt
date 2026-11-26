<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\ExpenseType;
use App\Models\WorkerPayroll;
use App\Services\DocumentNumber;
use App\Services\WalletService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Pengeluaran selain belanja bahan yang dicatat stoknya. Status data
 * "PERLU DIRINCI" menandai belanja yang isi barangnya belum jelas.
 */
class ExpenseController extends Controller
{
    public const DATA_STATUSES = ['ESTIMASI', 'PERLU DIRINCI'];

    public function index(Request $request): View
    {
        $query = Expense::query()->with(['category', 'wallet'])
            ->when($request->input('month'), fn ($query, $month) => $query->where('spent_at', 'like', "{$month}%"))
            ->when($request->input('category'), fn ($query, $category) => $query->where('expense_category_id', $category))
            ->when($request->input('data_status'), fn ($query, $status) => $query->where('data_status', $status))
            ->when($request->string('q')->trim()->value(), fn ($query, $search) => $query->where(
                fn ($query) => $query->where('description', 'like', "%{$search}%")->orWhere('notes', 'like', "%{$search}%")
            ));

        return view('admin.expenses.index', [
            'total' => (clone $query)->sum('amount'),
            'expenses' => $query->latest('spent_at')->latest('id')->paginate(25)->withQueryString(),
            'categories' => ExpenseCategory::orderBy('name')->get(),
            'dataStatuses' => self::DATA_STATUSES,
        ]);
    }

    public function create(): View
    {
        return $this->form(new Expense(['spent_at' => now()]));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $expense = Expense::create([
            ...$data,
            'reference_number' => DocumentNumber::next('expenses', Carbon::parse($data['spent_at'])),
            'status' => 'posted',
            'posted_at' => now(),
        ]);

        $this->syncWallet($expense);

        return redirect()->route('admin.expenses.index')->with('status', 'Pengeluaran dicatat.');
    }

    public function edit(Expense $expense): View|RedirectResponse
    {
        if ($slip = $this->payrollOf($expense)) {
            return redirect()->route('admin.payrolls.show', $slip)->with('error', 'Pengeluaran ini dari pembayaran gaji. Ubah atau batalkan lewat slip gajinya.');
        }

        return $this->form($expense);
    }

    public function update(Request $request, Expense $expense): RedirectResponse
    {
        if ($slip = $this->payrollOf($expense)) {
            return redirect()->route('admin.payrolls.show', $slip)->with('error', 'Pengeluaran ini dari pembayaran gaji. Ubah atau batalkan lewat slip gajinya.');
        }

        $expense->update($this->validated($request));
        $this->syncWallet($expense->refresh());

        return redirect()->route('admin.expenses.index')->with('status', 'Pengeluaran diperbarui.');
    }

    public function destroy(Expense $expense): RedirectResponse
    {
        if ($this->payrollOf($expense)) {
            return back()->with('error', 'Pengeluaran ini dari pembayaran gaji. Batalkan pembayarannya lewat slip gaji, bukan dari sini.');
        }

        app(WalletService::class)->forget($expense);
        $expense->delete();

        return redirect()->route('admin.expenses.index')->with('status', 'Pengeluaran dihapus.');
    }

    private function form(Expense $expense): View
    {
        return view('admin.expenses.form', [
            'expense' => $expense,
            'categories' => ExpenseCategory::where('is_active', true)->orderBy('name')->get(),
            'dataStatuses' => self::DATA_STATUSES,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'spent_at' => ['required', 'date'],
            'category' => ['required', 'string', 'max:100'],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'paid_to' => ['nullable', 'string', 'max:255'],
            'from_account' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'data_status' => ['nullable', Rule::in(self::DATA_STATUSES)],
            'wallet_id' => ['nullable', 'exists:wallets,id'],
        ]);

        // Kategori diketik bebas; kategori baru dibuat otomatis.
        $name = trim($data['category']);
        $type = ExpenseType::firstOrCreate(['slug' => 'usaha-knalpot'], ['name' => 'Usaha Knalpot']);
        $category = ExpenseCategory::query()->where('name', $name)->first()
            ?? ExpenseCategory::create(['name' => $name, 'slug' => $this->uniqueSlug($name), 'expense_type_id' => $type->id]);
        unset($data['category']);

        return $data + ['expense_category_id' => $category->id];
    }

    private function uniqueSlug(string $name): string
    {
        $slug = Str::slug($name) ?: 'kategori';

        for ($i = 2, $base = $slug; ExpenseCategory::where('slug', $slug)->exists(); $i++) {
            $slug = $base.'-'.$i;
        }

        return $slug;
    }

    /** Uang keluar dari dompet yang dipilih. */
    /** Slip gaji yang membuat pengeluaran ini (bila ada). */
    private function payrollOf(Expense $expense): ?WorkerPayroll
    {
        return WorkerPayroll::query()->where('expense_id', $expense->id)->orWhere('deduction_expense_id', $expense->id)->first();
    }

    private function syncWallet(Expense $expense): void
    {
        app(WalletService::class)->sync(
            $expense, $expense->wallet_id, 'out', (float) $expense->amount, $expense->spent_at,
            ($expense->category?->name ? $expense->category->name.': ' : '').$expense->description,
        );
    }
}
