<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Models\VendorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** Master Jasa Vendor: vendor + proses + tarif per satuan. */
class VendorServiceController extends Controller
{
    public function index(Request $request): View
    {
        $services = VendorService::query()->with('supplier')->withCount(['components', 'formulas'])
            ->when($request->string('q')->trim()->value(), fn ($query, $search) => $query->where(
                fn ($query) => $query->where('process', 'like', "%{$search}%")->orWhereHas('supplier', fn ($query) => $query->where('name', 'like', "%{$search}%"))
            ))
            ->get()->sortBy(fn (VendorService $service) => [$service->supplier->name, $service->process])->values();

        return view('admin.vendor-services.index', [
            'services' => $services,
            'vendors' => Supplier::query()->active()->orderBy('name')->pluck('name'),
            'processes' => VendorService::query()->distinct()->orderBy('process')->pluck('process'),
        ]);
    }

    /** Tambah jasa; tombol "+" di form formula memakai JSON. */
    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $data = $this->validated($request);
        $supplier = Supplier::resolve($data['vendor']);
        $exists = VendorService::query()->where('supplier_id', $supplier->id)->whereRaw('LOWER(process) = ?', [mb_strtolower(trim($data['process']))])->first();

        if ($exists) {
            $message = "Jasa {$exists->process} dari {$supplier->name} sudah ada (".rupiah($exists->rate).'/'.$exists->unit.').';

            return $request->expectsJson()
                ? response()->json(['message' => $message, 'errors' => ['process' => [$message]]], 422)
                : back()->withInput()->with('error', $message);
        }

        $service = VendorService::create([
            'supplier_id' => $supplier->id, 'process' => trim($data['process']), 'rate' => (float) $data['rate'],
            'unit' => $data['unit'] ?? 'buah', 'notes' => $data['notes'] ?? null,
        ])->load('supplier');

        return $request->expectsJson()
            ? response()->json($service->toOption() + ['message' => 'Jasa vendor disimpan.'])
            : back()->with('status', "Jasa {$service->process} dari {$supplier->name} disimpan.");
    }

    /** Ubah tarif/nama proses; formula yang memakai jasa ini ikut terbarui (HPP ikut). */
    public function update(Request $request, VendorService $service): RedirectResponse
    {
        $data = $this->validated($request);

        DB::transaction(function () use ($service, $data, $request) {
            $service->update([
                'supplier_id' => Supplier::resolve($data['vendor'])->id,
                'process' => trim($data['process']),
                'rate' => (float) $data['rate'],
                'unit' => $data['unit'] ?? $service->unit,
                'notes' => $data['notes'] ?? null,
                'is_active' => $request->boolean('is_active', true),
            ]);
            $service->load('supplier')->syncUsages();
        });

        return back()->with('status', 'Jasa vendor disimpan; formula yang memakainya ikut terbarui.');
    }

    public function destroy(VendorService $service): RedirectResponse
    {
        if ($used = $service->usageCount()) {
            return back()->with('error', "Jasa ini masih dipakai {$used} komponen/formula. Nonaktifkan saja bila tidak dipakai lagi.");
        }

        $service->delete();

        return back()->with('status', 'Jasa vendor dihapus.');
    }

    /** @return array{vendor: string, process: string, rate: float|string, unit?: ?string, notes?: ?string} */
    private function validated(Request $request): array
    {
        return $request->validate([
            'vendor' => ['required', 'string', 'max:255'],
            'process' => ['required', 'string', 'max:100'],
            'rate' => ['required', 'numeric', 'min:0'],
            'unit' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string'],
        ], [
            'vendor.required' => 'Isi nama vendor.',
            'process.required' => 'Isi prosesnya, mis. Bending.',
            'rate.required' => 'Isi tarifnya.',
        ]);
    }
}
