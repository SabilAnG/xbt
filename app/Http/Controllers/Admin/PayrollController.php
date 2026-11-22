<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Host;
use App\Models\HostPayroll;
use App\Models\Worker;
use App\Models\WorkerPayroll;
use App\Services\DocumentNumber;
use App\Services\WalletService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Sheet "Gaji Host" dan "Gaji Tukang". Jumlahnya selalu dihitung dari Jam
 * Live / Absen Tukang dan tarif di data host/tukang, persis rumus Excel.
 * Saat status diubah ke "Sudah", admin bisa sekaligus mencatatnya ke
 * Pengeluaran (kategori Host Live / Gaji Tukang).
 */
class PayrollController extends Controller
{
    /** @var array<string, array{model: class-string<Model>, person: class-string<Model>, key: string, category: string, title: string}> */
    private const KINDS = [
        'host' => ['model' => HostPayroll::class, 'person' => Host::class, 'key' => 'host_id', 'category' => 'Host Live', 'title' => 'Gaji Host'],
        'tukang' => ['model' => WorkerPayroll::class, 'person' => Worker::class, 'key' => 'worker_id', 'category' => 'Gaji Tukang', 'title' => 'Gaji Tukang'],
    ];

    public function index(Request $request, string $kind): View
    {
        $config = $this->config($kind);
        $relation = $kind === 'host' ? 'host' : 'worker';

        $rows = $config['model']::query()->with($relation)
            ->when($request->input('status'), fn ($query, $status) => $query->where('status', $status))
            ->when($request->input('person'), fn ($query, $person) => $query->where($config['key'], $person))
            ->latest('period_start')->orderBy($config['key'])
            ->paginate(40)->withQueryString();

        $unpaid = $config['model']::query()->with($relation)->where('status', 'Belum')->get()->sum(fn ($row) => $row->amountDue());

        return view('admin.payrolls.index', [
            'kind' => $kind,
            'title' => $config['title'],
            'rows' => $rows,
            'unpaid' => $unpaid,
            'people' => $config['person']::orderBy('name')->get(),
        ]);
    }

    public function create(string $kind): View
    {
        $config = $this->config($kind);
        // Default: minggu lalu Senin–Sabtu (tukang) / Minggu–Sabtu (host).
        $start = now()->subWeek()->startOfWeek(Carbon::MONDAY);

        return view('admin.payrolls.form', [
            'kind' => $kind,
            'title' => $config['title'],
            'row' => new $config['model'](['period_start' => $start, 'period_end' => $start->copy()->addDays(5), 'status' => 'Belum']),
            'people' => $config['person']::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, string $kind): RedirectResponse
    {
        $config = $this->config($kind);
        $data = $this->validated($request, $kind);
        $people = $data['people'];
        unset($data['people']);

        foreach ($people as $personId) {
            $row = $config['model']::create([...$data, $config['key'] => $personId]);
            $this->recordExpenseIfRequested($request, $kind, $row);
        }

        return redirect()->route('admin.payrolls.index', $kind)->with('status', count($people).' rekap '.strtolower($config['title']).' dibuat.');
    }

    public function edit(string $kind, int $id): View
    {
        $config = $this->config($kind);

        return view('admin.payrolls.form', [
            'kind' => $kind,
            'title' => $config['title'],
            'row' => $config['model']::findOrFail($id),
            'people' => $config['person']::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, string $kind, int $id): RedirectResponse
    {
        $config = $this->config($kind);
        $row = $config['model']::findOrFail($id);
        $wasPaid = $row->status === 'Sudah';
        $data = $this->validated($request, $kind, editing: true);
        $row->update([...$data, $config['key'] => $data['people'][0]]);

        if (! $wasPaid) {
            $this->recordExpenseIfRequested($request, $kind, $row);
        }

        return redirect()->route('admin.payrolls.index', $kind)->with('status', 'Rekap disimpan.');
    }

    public function destroy(string $kind, int $id): RedirectResponse
    {
        $this->config($kind)['model']::findOrFail($id)->delete();

        return back()->with('status', 'Rekap dihapus.');
    }

    /**
     * @return array{model: class-string<Model>, person: class-string<Model>, key: string, category: string, title: string}
     */
    private function config(string $kind): array
    {
        abort_unless(isset(self::KINDS[$kind]), 404);

        return self::KINDS[$kind];
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, string $kind, bool $editing = false): array
    {
        $table = $kind === 'host' ? 'hosts' : 'workers';
        $rules = [
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'people' => ['required', 'array', $editing ? 'size:1' : 'min:1'],
            'people.*' => ['integer', "exists:{$table},id"],
            'status' => ['required', Rule::in(['Belum', 'Sudah'])],
            'paid_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:255'],
            'wallet_id' => ['nullable', 'exists:wallets,id'],
        ];

        if ($kind === 'tukang') {
            $rules['bonus'] = ['nullable', 'numeric', 'min:0'];
            $rules['deduction'] = ['nullable', 'numeric', 'min:0'];
        }

        $data = $request->validate($rules);
        unset($data['wallet_id']);

        if ($kind === 'tukang') {
            $data['bonus'] = $data['bonus'] ?? 0;
            $data['deduction'] = $data['deduction'] ?? 0;
        }

        return $data;
    }

    private function recordExpenseIfRequested(Request $request, string $kind, Model $row): void
    {
        if ($row->status !== 'Sudah' || ! $request->boolean('record_expense') || $row->amountDue() <= 0) {
            return;
        }

        $config = $this->config($kind);
        $category = ExpenseCategory::where('name', $config['category'])->first();
        $person = $kind === 'host' ? $row->host : $row->worker;

        if (! $category) {
            return;
        }

        $date = $row->paid_at ?? now();

        $expense = Expense::create([
            'reference_number' => DocumentNumber::next('expenses', $date),
            'spent_at' => $date->toDateString(),
            'expense_category_id' => $category->id,
            'amount' => $row->amountDue(),
            'description' => "{$config['title']} {$person?->name} {$row->period_start->format('d/m')}–{$row->period_end->format('d/m/Y')}",
            'paid_to' => $person?->account,
            'wallet_id' => $request->input('wallet_id') ?: null,
            'status' => 'posted',
            'posted_at' => now(),
        ]);

        app(WalletService::class)->sync($expense, $expense->wallet_id, 'out', (float) $expense->amount, $expense->spent_at, $expense->description);
    }
}
