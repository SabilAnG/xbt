<?php

namespace App\Filament\Resources\Formulas\Tables;

use App\Models\Formula;
use App\Models\PriceTier;
use App\Support\TableActions;
use App\Support\TableExport;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class FormulasTable
{
    public static function configure(Table $table): Table
    {
        $guard = TableActions::notInUse(['productions' => 'nota produksi']);

        // Satu kolom harga per tingkatan, ikut berubah bila tingkatannya
        // ditambah atau dihapus — tidak ada nama tingkatan yang dipatok.
        $tingkatan = PriceTier::where('is_active', true)->orderBy('sort_order')->get();

        $kolomHarga = $tingkatan
            ->mapWithKeys(fn (PriceTier $t) => [
                'Harga '.$t->name => fn (Formula $r) => $t->price($r->hppPerUnit()),
            ])
            ->all();

        // Kolom harga di layar memakai satu tingkatan saja supaya tabel tidak
        // melebar: margin tertinggi yang tanpa potongan marketplace.
        $tierUtama = $tingkatan->where('fee_percent', 0)->sortByDesc('margin_percent')->first()
            ?? $tingkatan->first();

        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->label('Formula')->searchable()->sortable()->wrap()->weight('medium')
                    ->description(fn (Formula $r) => $r->code),

                TextColumn::make('motorcycleModel.name')->label('Type Motor')
                    ->badge()->color('gray')->placeholder('—')->toggleable(),

                // Tanpa barang jual, hasil produksi tidak punya tempat masuk —
                // HPP terhitung tapi knalpotnya tidak pernah bisa dijual.
                TextColumn::make('item.name')->label('Barang Jual')
                    ->badge()->color('success')
                    ->placeholder('belum dihubungkan')
                    ->tooltip(fn (Formula $r) => $r->item_id
                        ? null
                        : 'Isi "Barang jual yang dihasilkan" agar hasil produksi otomatis masuk Stok Barang.')
                    ->toggleable(),

                TextColumn::make('hasil')
                    ->label('Hasil')
                    ->alignCenter()
                    ->toggleable()
                    ->getStateUsing(fn (Formula $r) => rtrim(rtrim((string) $r->output_qty, '0'), '.').' '.$r->output_unit),

                // Kolom pendukung disembunyikan dulu supaya tabel tetap
                // terbaca di layar kecil; bisa dimunculkan lewat tombol kolom.
                TextColumn::make('materials_count')->counts('materials')->label('Bahan')->alignCenter()->badge()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('costs_count')->counts('costs')->label('Proses')->alignCenter()->badge()->color('info')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('menit')
                    ->label('Waktu')
                    ->alignRight()
                    ->getStateUsing(function (Formula $r) {
                        $m = $r->loadMissing('costs.component')->totalMinutes();
                        $jam = floor($m / 60);

                        return $jam > 0 ? sprintf('%dj %dm', $jam, $m - $jam * 60) : sprintf('%dm', $m);
                    })
                    ->description('kerja per resep')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('hpp')
                    ->label('HPP / unit')
                    ->alignRight()
                    ->weight('bold')
                    ->money('IDR')
                    ->getStateUsing(fn (Formula $r) => $r->loadMissing([
                        'materials.material', 'costs.component', 'machines.machine',
                    ])->hppPerUnit())
                    ->description('bahan + kerja + mesin + overhead'),

                TextColumn::make('harga_jual')
                    ->label('Harga Jual')
                    ->alignRight()
                    ->money('IDR')
                    ->color('success')
                    ->getStateUsing(fn (Formula $r) => $tierUtama?->price($r->hppPerUnit()))
                    ->description($tierUtama
                        ? $tierUtama->name.' · margin '.rtrim(rtrim((string) $tierUtama->margin_percent, '0'), '.').'%'
                        : 'belum ada tingkatan harga')
                    ->placeholder('—'),

                TextColumn::make('kapasitas')
                    ->label('Bisa dibuat')
                    ->alignRight()
                    ->badge()
                    ->getStateUsing(function (Formula $r) {
                        $k = $r->loadMissing('materials.material')->capacity();

                        return rtrim(rtrim((string) $k['unit'], '0'), '.').' '.$r->output_unit;
                    })
                    ->color(fn (Formula $r) => $r->capacity()['unit'] > 0 ? 'success' : 'danger')
                    ->description(fn (Formula $r) => ($p = $r->capacity()['pembatas']) ? 'dibatasi '.$p : null),

                IconColumn::make('is_active')->label('Aktif')->boolean()->toggleable(),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label('Status')->placeholder('Semua'),
            ])
            ->recordActions([
                EditAction::make()->label('Ubah'),
                TableActions::delete($guard),
            ])
            ->headerActions([
                TableExport::action('formula-hpp', [
                    'Kode' => fn (Formula $r) => $r->code,
                    'Formula' => fn (Formula $r) => $r->name,
                    'Type Motor' => fn (Formula $r) => $r->motorcycleModel?->name,
                    'Hasil per Resep' => fn (Formula $r) => (float) $r->output_qty,
                    'Satuan' => fn (Formula $r) => $r->output_unit,
                    'Biaya Bahan' => fn (Formula $r) => $r->loadMissing('materials.material')->materialCost(),
                    'Biaya Tenaga Kerja' => fn (Formula $r) => $r->loadMissing('costs.component')->serviceCost(),
                    'Total Menit Kerja' => fn (Formula $r) => $r->totalMinutes(),
                    'Biaya Mesin' => fn (Formula $r) => $r->loadMissing('machines.machine')->machineCost(),
                    'Overhead' => fn (Formula $r) => $r->overheadCost(),
                    'HPP per Unit' => fn (Formula $r) => $r->hppPerUnit(),
                    'Bisa Dibuat' => fn (Formula $r) => $r->capacity()['unit'],
                    'Dibatasi' => fn (Formula $r) => $r->capacity()['pembatas'],
                ] + $kolomHarga),
            ])
            ->toolbarActions([
                BulkActionGroup::make([TableActions::deleteBulk($guard)]),
            ])
            ->emptyStateHeading('Belum ada formula')
            ->emptyStateDescription('Buat resep, mis. "Knalpot Mio 1 set" berisi pipa, plat, inlet, dan jasa las.')
            ->emptyStateIcon('heroicon-o-beaker');
    }
}
