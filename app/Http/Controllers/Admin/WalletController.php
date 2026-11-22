<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\WalletTransfer;
use App\Services\WalletService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * Dompet: rekening bank, e-wallet, dan kas tunai. Saldo berjalan dari mutasi
 * penjualan (masuk), pengeluaran & pembelian bahan (keluar), transfer antar
 * dompet, dan mutasi manual.
 */
class WalletController extends Controller
{
    public function index(): View
    {
        $wallets = Wallet::query()->withCount('transactions')->orderBy('sort_order')->orderBy('name')->get();

        return view('admin.wallets.index', [
            'wallets' => $wallets,
            'total' => $wallets->where('is_active', true)->sum('current_balance'),
            'recent' => WalletTransaction::query()->with('wallet')->latest('occurred_at')->latest('id')->limit(10)->get(),
        ]);
    }

    public function show(Request $request, Wallet $wallet): View
    {
        $rows = $wallet->transactions()->with('source')
            ->when($request->input('month'), fn ($q, $month) => $q->where('occurred_at', 'like', "{$month}%"))
            ->when($request->input('direction'), fn ($q, $direction) => $q->where('direction', $direction))
            ->orderByDesc('occurred_at')->orderByDesc('id')
            ->paginate(30)->withQueryString();

        return view('admin.wallets.show', [
            'wallet' => $wallet,
            'rows' => $rows,
            'totalIn' => (float) $wallet->transactions()->where('direction', 'in')->sum('amount'),
            'totalOut' => (float) $wallet->transactions()->where('direction', 'out')->sum('amount'),
        ]);
    }

    public function create(): View
    {
        return view('admin.wallets.form', ['wallet' => new Wallet(['type' => 'bank', 'is_active' => true])]);
    }

    public function store(Request $request, WalletService $service): RedirectResponse
    {
        $wallet = Wallet::create([...$this->validated($request), 'sort_order' => (int) Wallet::max('sort_order') + 1]);
        $service->recalculate([$wallet->id]);

        return redirect()->route('admin.wallets.index')->with('status', "Dompet {$wallet->name} ditambahkan.");
    }

    public function edit(Wallet $wallet): View
    {
        return view('admin.wallets.form', ['wallet' => $wallet]);
    }

    public function update(Request $request, Wallet $wallet, WalletService $service): RedirectResponse
    {
        $wallet->update($this->validated($request, $wallet));
        $service->recalculate([$wallet->id]);

        return redirect()->route('admin.wallets.show', $wallet)->with('status', 'Dompet disimpan. Saldo dihitung ulang.');
    }

    public function destroy(Wallet $wallet): RedirectResponse
    {
        if ($wallet->transactions()->exists()) {
            return back()->with('error', 'Dompet sudah punya mutasi, jadi tidak bisa dihapus. Nonaktifkan saja.');
        }

        $wallet->delete();

        return redirect()->route('admin.wallets.index')->with('status', 'Dompet dihapus.');
    }

    public function createTransfer(Request $request): View
    {
        return view('admin.wallets.transfer', ['wallets' => Wallet::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get()]);
    }

    public function storeTransfer(Request $request, WalletService $service): RedirectResponse
    {
        $data = $request->validate([
            'from_wallet_id' => ['required', 'exists:wallets,id'],
            'to_wallet_id' => ['required', 'exists:wallets,id', 'different:from_wallet_id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'transferred_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:255'],
        ], ['to_wallet_id.different' => 'Dompet tujuan harus berbeda dari dompet asal.']);

        try {
            $service->transfer(Wallet::findOrFail($data['from_wallet_id']), Wallet::findOrFail($data['to_wallet_id']), (float) $data['amount'], $data['transferred_at'], $data['notes'] ?? null);
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['amount' => $e->getMessage()]);
        }

        return redirect()->route('admin.wallets.index')->with('status', 'Transfer dicatat.');
    }

    public function destroyTransfer(WalletTransfer $transfer, WalletService $service): RedirectResponse
    {
        $service->deleteTransfer($transfer);

        return back()->with('status', 'Transfer dihapus dan saldo dikembalikan.');
    }

    public function createManual(Request $request): View
    {
        return view('admin.wallets.manual', ['wallets' => Wallet::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get()]);
    }

    public function storeManual(Request $request, WalletService $service): RedirectResponse
    {
        $data = $request->validate([
            'wallet_id' => ['required', 'exists:wallets,id'],
            'direction' => ['required', Rule::in(['in', 'out'])],
            'amount' => ['required', 'numeric', 'gt:0'],
            'occurred_at' => ['required', 'date'],
            'description' => ['required', 'string', 'max:255'],
        ]);

        $wallet = Wallet::findOrFail($data['wallet_id']);
        $service->manual($wallet, $data['direction'], (float) $data['amount'], $data['occurred_at'], $data['description']);

        return redirect()->route('admin.wallets.show', $wallet)->with('status', 'Mutasi dicatat.');
    }

    public function destroyTransaction(WalletTransaction $transaction, WalletService $service): RedirectResponse
    {
        try {
            $service->deleteManual($transaction);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', 'Mutasi dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Wallet $wallet = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('wallets', 'name')->ignore($wallet)],
            'type' => ['required', Rule::in(array_keys(Wallet::TYPES))],
            'account_number' => ['nullable', 'string', 'max:100'],
            'holder_name' => ['nullable', 'string', 'max:100'],
            'opening_balance' => ['nullable', 'numeric'],
        ]);

        return [...$data, 'opening_balance' => $data['opening_balance'] ?? 0, 'is_active' => $request->boolean('is_active')];
    }
}
