<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\StoresSiteUploads;
use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\ItemAdjustment;
use App\Models\Material;
use App\Models\MaterialAdjustment;
use App\Models\PipePiece;
use App\Models\StockPhoto;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Worker;
use App\Services\AdjustmentService;
use App\Services\InventoryService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;
use RuntimeException;

/**
 * Stok opname per gudang: hitung fisik banyak barang sekaligus. Hanya baris
 * yang stok fisiknya diisi dan berbeda dari sistem yang dibuatkan
 * penyesuaian. Pipa dihitung per batang lewat Penyesuaian Stok.
 */
class StockOpnameController extends Controller
{
    use StoresSiteUploads;

    /** Gudang yang bisa diopname di halaman ini, urut tampil. */
    private const WAREHOUSES = [Warehouse::FINISHED, Warehouse::RAW, Warehouse::SEMI, Warehouse::ACCESSORY, Warehouse::OFFCUT];

    public function index(Request $request): View
    {
        $warehouses = collect(self::WAREHOUSES)->map(fn (string $type) => Warehouse::forType($type));
        $current = $warehouses->first(fn (Warehouse $warehouse) => in_array($request->input('gudang'), [$warehouse->slug(), $warehouse->type], true)) ?? $warehouses->first();

        return view('admin.stock-opname.index', [
            'warehouse' => $current,
            'tiles' => $warehouses->map(function (Warehouse $warehouse) {
                $rows = $this->rows($warehouse);

                $pieces = $this->piecesIn($warehouse);

                return [
                    'warehouse' => $warehouse,
                    'count' => $rows->count() + $pieces->count(),
                    'value' => (float) $rows->sum(fn (array $row) => $row['system'] * $row['cost']) + (float) $pieces->sum(fn (PipePiece $piece) => $piece->value()),
                ];
            }),
            'rows' => $this->rows($current),
            'pieces' => $this->piecesIn($current),
            'reasons' => ItemAdjustment::REASONS,
            'lastCount' => $this->lastCount($current),
            // Saran nama pelaksana: pekerja aktif & pengguna panel.
            'counters' => Worker::query()->where('is_active', true)->orderBy('name')->pluck('name')
                ->merge(User::query()->where('is_active', true)->orderBy('name')->pluck('name'))->unique()->values(),
        ]);
    }

    public function storeItems(Request $request, InventoryService $inventory): RedirectResponse
    {
        $data = $request->validate([
            'adjusted_at' => ['required', 'date'],
            'counted_by' => ['required', 'string', 'max:100'],
            'reason' => ['required', Rule::in(array_keys(ItemAdjustment::REASONS))],
            'notes' => ['nullable', 'string', 'max:255'],
            'actual' => ['array'],
            'actual.*' => ['nullable', 'numeric', 'min:0'],
            'photos' => ['array'],
            'photos.*' => ['image', 'max:12288'],
            'piece_photos' => ['array'],
            'piece_photos.*' => ['image', 'max:12288'],
        ], ['photos.*.image' => 'Berkas foto harus gambar (JPG/PNG/WebP).', 'photos.*.max' => 'Foto maksimal 12 MB.', 'piece_photos.*.max' => 'Foto maksimal 12 MB.']);

        $items = Item::query()->whereIn('id', array_keys(array_filter($data['actual'] ?? [], 'filled')))->get();
        $changed = $items->filter(fn (Item $item) => abs((float) $data['actual'][$item->id] - (float) $item->stock) > 0.0001);
        $photos = $request->file('photos', []);

        if ($changed->isEmpty() && $photos === []) {
            return back()->withInput()->with('error', 'Tidak ada stok fisik yang berbeda dari stok sistem, dan belum ada foto.');
        }

        try {
            DB::transaction(fn () => $changed->each(fn (Item $item) => $inventory->adjustItem($item, (float) $data['actual'][$item->id], $data['reason'], $data['adjusted_at'], ($data['notes'] ?? null) ?: 'Stok opname', $data['counted_by'])));
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $finished = Warehouse::forType(Warehouse::FINISHED);
        foreach (Item::query()->whereIn('id', array_keys($photos))->get() as $item) {
            $this->savePhoto($item, $photos[$item->id], $finished, $data, $data['actual'][$item->id] ?? $item->stock);
        }

        return redirect()->route('admin.stock-opname.index')->with('status', 'Stok opname Finish Good disimpan: '.$changed->count().' produk disesuaikan'.($photos ? ', '.count($photos).' foto' : '').'.');
    }

    public function storeMaterials(Request $request, AdjustmentService $adjustments): RedirectResponse
    {
        $data = $request->validate([
            'warehouse' => ['required', Rule::in([Warehouse::RAW, Warehouse::SEMI, Warehouse::ACCESSORY, Warehouse::OFFCUT])],
            'adjusted_at' => ['required', 'date'],
            'counted_by' => ['required', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:255'],
            'actual' => ['array'],
            'actual.*' => ['nullable', 'numeric', 'min:0'],
            'pieces_listed' => ['array'],
            'pieces_listed.*' => ['integer'],
            'pieces_present' => ['array'],
            'pieces_present.*' => ['integer'],
            'photos' => ['array'],
            'photos.*' => ['image', 'max:12288'],
            'piece_photos' => ['array'],
            'piece_photos.*' => ['image', 'max:12288'],
        ], ['photos.*.image' => 'Berkas foto harus gambar (JPG/PNG/WebP).', 'photos.*.max' => 'Foto maksimal 12 MB.', 'piece_photos.*.max' => 'Foto maksimal 12 MB.']);
        $warehouse = Warehouse::forType($data['warehouse']);
        // Batang/potongan pipa yang ditampilkan tapi tidak dicentang "ada" = hilang.
        $missing = $this->piecesIn($warehouse)->whereIn('id', $data['pieces_listed'] ?? [])->whereNotIn('id', $data['pieces_present'] ?? []);
        $materials = $this->materialsIn($warehouse)->whereIn('id', array_keys(array_filter($data['actual'] ?? [], 'filled')));
        $changed = $materials->filter(fn (Material $material) => abs((float) $data['actual'][$material->id] - $material->stockIn($warehouse)) > 0.0001);

        $photos = $request->file('photos', []);
        $piecePhotos = $request->file('piece_photos', []);

        if ($changed->isEmpty() && $missing->isEmpty() && $photos === [] && $piecePhotos === []) {
            return back()->withInput()->with('error', 'Tidak ada stok fisik yang berbeda dari stok sistem, dan belum ada foto.');
        }

        try {
            DB::transaction(fn () => $missing->each(fn (PipePiece $piece) => $adjustments->create([
                'adjusted_at' => $data['adjusted_at'], 'material_id' => $piece->material_id, 'warehouse_id' => $warehouse->id,
                'direction' => 'out', 'pipe_piece_id' => $piece->id, 'reason' => 'koreksi', 'counted_by' => $data['counted_by'],
                'notes' => trim((($data['notes'] ?? null) ?: 'Stok opname').' · '.$piece->code.' tidak ditemukan'),
            ])));
            DB::transaction(fn () => $changed->each(function (Material $material) use ($data, $warehouse, $adjustments) {
                $difference = (float) $data['actual'][$material->id] - $material->stockIn($warehouse);
                $adjustments->create([
                    'adjusted_at' => $data['adjusted_at'], 'material_id' => $material->id, 'warehouse_id' => $warehouse->id,
                    'direction' => $difference > 0 ? 'in' : 'out', 'qty' => abs($difference), 'reason' => 'koreksi', 'counted_by' => $data['counted_by'],
                    'notes' => ($data['notes'] ?? null) ?: 'Stok opname',
                ]);
            }));
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        foreach ($this->materialsIn($warehouse)->whereIn('id', array_keys($photos)) as $material) {
            $this->savePhoto($material, $photos[$material->id], $warehouse, $data, $data['actual'][$material->id] ?? $material->stockIn($warehouse));
        }
        foreach (PipePiece::query()->whereIn('id', array_keys($piecePhotos))->get() as $piece) {
            $this->savePhoto($piece, $piecePhotos[$piece->id], $warehouse, $data, null);
        }

        return redirect()->route('admin.stock-opname.index', ['gudang' => $warehouse->slug()])
            ->with('status', 'Stok opname '.$warehouse->name.' disimpan: '.$changed->count().' bahan disesuaikan'.($missing->isNotEmpty() ? ', '.$missing->count().' potongan pipa hilang' : '').'.');
    }

    /**
     * Baris opname untuk satu gudang (produk jadi atau bahan per satuan).
     *
     * @return Collection<int, array{id: int, name: string, meta: string, unit: string, system: float, cost: float, href: ?string}>
     */
    private function rows(Warehouse $warehouse): Collection
    {
        if ($warehouse->type === Warehouse::FINISHED) {
            return Item::query()->where('is_active', true)->with(['stockPhotos' => fn ($query) => $query->limit(1)])->orderBy('name')->get()->map(fn (Item $item) => [
                'id' => $item->id, 'name' => $item->name, 'meta' => $item->sku, 'unit' => $item->unit ?: 'pcs',
                'system' => (float) $item->stock, 'cost' => (float) $item->cost_price, 'href' => route('admin.items.show', $item),
                'photo' => $item->stockPhotos->first(),
            ]);
        }

        return $this->materialsIn($warehouse)->map(fn (Material $material) => [
            'id' => $material->id, 'name' => $material->name, 'meta' => $material->category?->name ?? '', 'unit' => $material->stockUnit(),
            'system' => $material->stockIn($warehouse), 'cost' => $material->unitCost(), 'href' => route('admin.materials.show', $material),
            'photo' => $material->stockPhotos()->where('warehouse_id', $warehouse->id)->first(),
        ]);
    }

    /** @param  array{adjusted_at: string, counted_by: string}  $data */
    private function savePhoto(Model $subject, UploadedFile $file, Warehouse $warehouse, array $data, float|string|null $qty): void
    {
        StockPhoto::create([
            'subject_type' => $subject->getMorphClass(), 'subject_id' => $subject->getKey(), 'warehouse_id' => $warehouse->id,
            'path' => $this->storeSiteUpload($file, 'stok-opname'), 'taken_at' => $data['adjusted_at'], 'counted_by' => $data['counted_by'],
            'counted_qty' => $qty === null || $qty === '' ? null : (float) $qty,
        ]);
    }

    /** Opname terakhir di gudang ini: tanggal & pelaksana. */
    private function lastCount(Warehouse $warehouse): ?object
    {
        $query = $warehouse->type === Warehouse::FINISHED
            ? ItemAdjustment::query()
            : MaterialAdjustment::query()->where('warehouse_id', $warehouse->id);

        return $query->whereNotNull('counted_by')->latest('adjusted_at')->latest('id')->first(['adjusted_at', 'counted_by']);
    }

    /**
     * Batang/potongan pipa yang tersedia di gudang ini (dihitung per batang).
     *
     * @return Collection<int, PipePiece>
     */
    private function piecesIn(Warehouse $warehouse): Collection
    {
        if (! in_array($warehouse->type, [Warehouse::RAW, Warehouse::OFFCUT], true)) {
            return collect();
        }

        return PipePiece::query()->with(['material', 'stockPhotos'])->where('warehouse_id', $warehouse->id)->where('status', PipePiece::AVAILABLE)
            ->orderBy('material_id')->orderByDesc('length_cm')->get();
    }

    /** @return Collection<int, Material> */
    private function materialsIn(Warehouse $warehouse): Collection
    {
        return Material::query()->active()->with('stocks', 'category')->orderBy('name')->get()
            ->reject(fn (Material $material) => $material->isPipe())
            ->filter(fn (Material $material) => $material->warehouse_id === $warehouse->id || $material->stockIn($warehouse) > 0)
            ->values();
    }
}
