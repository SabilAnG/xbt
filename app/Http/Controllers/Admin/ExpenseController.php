<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Services\DocumentNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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
        $query = Expense::query()->with('category')
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

        Expense::create([
            ...$data,
            'reference_number' => DocumentNumber::next('expenses', Carbon::parse($data['spent_at'])),
            'status' => 'posted',
            'posted_at' => now(),
        ]);

        return redirect()->route('admin.expenses.index')->with('status', 'Pengeluaran dicatat.');
    }

    public function edit(Expense $expense): View
    {
        return $this->form($expense);
    }

    public function update(Request $request, Expense $expense): RedirectResponse
    {
        $expense->update($this->validated($request));

        return redirect()->route('admin.expenses.index')->with('status', 'Pengeluaran diperbarui.');
    }

    public function destroy(Expense $expense): RedirectResponse
    {
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
        return $request->validate([
            'spent_at' => ['required', 'date'],
            'expense_category_id' => ['required', 'exists:expense_categories,id'],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'paid_to' => ['nullable', 'string', 'max:255'],
            'from_account' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'data_status' => ['nullable', Rule::in(self::DATA_STATUSES)],
        ]);
    }
}
