<?php

namespace App\Filament\Resources\Formulas\Schemas;

use App\Models\CostComponent;
use App\Models\Formula;
use App\Models\FormulaMaterial;
use App\Models\Machine;
use App\Models\Material;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

/**
 * Form resep produksi.
 *
 * Disusun bertab karena satu formula bisa berisi belasan bahan, belasan proses,
 * dan beberapa mesin — kalau ditumpuk jadi satu halaman panjang, mengubah satu
 * angka berarti menggulir jauh. Tiap kolom diberi lebar per ukuran layar supaya
 * di HP tiap isian melebar penuh, bukan berdesakan dalam 12 kolom.
 */
class FormulaForm
{
    /** Lebar kolom yang sama dipakai berulang; ditulis sekali di sini. */
    private const SPAN_PENUH = ['default' => 1, 'sm' => 2, 'md' => 12];

    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make('formula')
                ->columnSpanFull()
                ->persistTabInQueryString()
                ->tabs([
                    Tab::make('Identitas')
                        ->icon('heroicon-o-identification')
                        ->schema(self::identitas()),

                    Tab::make('Bahan')
                        ->icon('heroicon-o-cube')
                        ->badge(fn (?Formula $record) => $record?->materials()->count() ?: null)
                        ->schema(self::bahan()),

                    Tab::make('Proses & Mesin')
                        ->icon('heroicon-o-wrench-screwdriver')
                        ->badge(fn (?Formula $record) => $record?->costs()->count() ?: null)
                        ->schema(self::prosesDanMesin()),

                    Tab::make('Hasil')
                        ->icon('heroicon-o-calculator')
                        ->schema(self::hasil()),
                ]),
        ]);
    }

    // -------------------------------------------------------------- identitas

    /** @return array<int, mixed> */
    private static function identitas(): array
    {
        return [
            Section::make('Resep')
                ->description('Satu formula = satu resep. Isi berapa unit yang dihasilkan sekali jalan.')
                ->columns(['default' => 1, 'md' => 6])
                ->schema([
                    TextInput::make('name')
                        ->label('Nama Formula')
                        ->required()->maxLength(255)
                        ->placeholder('Mio M3 125 — STD Racing V1')
                        ->columnSpan(['default' => 1, 'md' => 4])
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (?string $state, callable $get, callable $set) {
                            if (blank($get('code')) && filled($state)) {
                                $set('code', strtoupper(Str::slug($state)));
                            }
                        }),

                    TextInput::make('code')->label('Kode')->required()
                        ->unique(ignoreRecord: true)->maxLength(64)
                        ->columnSpan(['default' => 1, 'md' => 2]),

                    Select::make('motorcycle_model_id')
                        ->label('Type Motor')
                        ->relationship('motorcycleModel', 'name')
                        ->searchable()->preload()
                        ->columnSpan(['default' => 1, 'md' => 3]),

                    Select::make('item_id')
                        ->label('Barang jual yang dihasilkan')
                        ->relationship('item', 'name')
                        ->searchable()->preload()
                        ->columnSpan(['default' => 1, 'md' => 3])
                        ->helperText('Isi agar hasil produksi otomatis masuk Stok Barang dengan harga pokok = HPP.'),

                    TextInput::make('output_qty')
                        ->label('Hasil per resep')
                        ->numeric()->required()->default(1)->minValue(0.001)
                        ->columnSpan(['default' => 1, 'md' => 2]),

                    TextInput::make('output_unit')
                        ->label('Satuan')->default('set')->required()
                        ->columnSpan(['default' => 1, 'md' => 2]),

                    Toggle::make('is_active')->label('Aktif')->default(true)
                        ->columnSpan(['default' => 1, 'md' => 2]),
                ]),

            Section::make('Catatan')
                ->collapsed()
                ->schema([Textarea::make('notes')->hiddenLabel()->rows(3)]),
        ];
    }

    // ------------------------------------------------------------------ bahan

    /** @return array<int, mixed> */
    private static function bahan(): array
    {
        return [
            Section::make('Kebutuhan Bahan')
                ->description('Isi ukuran apa adanya — pipa dalam mm, plat dalam ukuran potongan, endcap cukup diameternya. Sistem yang menghitung luas, sisa potong, dan biayanya.')
                ->schema([
                    Repeater::make('materials')
                        ->relationship()
                        ->hiddenLabel()
                        ->addActionLabel('Tambah bahan')
                        ->orderColumn('sort_order')
                        ->collapsible()
                        ->collapseAllAction(fn ($action) => $action->label('Tutup semua'))
                        ->itemLabel(fn (array $state) => self::itemLabel($state))
                        ->columns(['default' => 1, 'sm' => 2, 'md' => 12])
                        ->schema([
                            Select::make('material_id')
                                ->label('Bahan')
                                ->options(fn () => Material::where('is_active', true)
                                    ->orderBy('name')->pluck('name', 'id'))
                                ->searchable()->required()
                                ->columnSpan(['default' => 1, 'sm' => 2, 'md' => 5])
                                ->live()
                                ->afterStateUpdated(function ($state, callable $set) {
                                    // Mode input menyesuaikan bentuk bahannya.
                                    $m = Material::find($state);
                                    $set('input_mode', match ($m?->dimension_type) {
                                        'linear' => 'length',
                                        'sheet' => 'rect',
                                        default => 'direct',
                                    });
                                }),

                            Select::make('bom_group')
                                ->label('Bagian')
                                ->options(FormulaMaterial::BOM_GROUPS)
                                ->default('other')
                                ->required()
                                ->columnSpan(['default' => 1, 'sm' => 1, 'md' => 4]),

                            Select::make('input_mode')
                                ->label('Cara isi')
                                ->options(fn (callable $get) => FormulaMaterial::modesFor(
                                    Material::find($get('material_id'))?->dimension_type
                                ))
                                ->default('direct')
                                ->required()
                                ->live()
                                ->columnSpan(['default' => 1, 'sm' => 1, 'md' => 3]),

                            TextInput::make('piece_length_mm')
                                ->label('Panjang')->suffix('mm')
                                ->numeric()->minValue(0)->live(onBlur: true)
                                ->columnSpan(['default' => 1, 'sm' => 1, 'md' => 3])
                                ->visible(fn (callable $get) => in_array($get('input_mode'), ['length', 'rect'], true)),

                            TextInput::make('piece_width_mm')
                                ->label('Lebar')->suffix('mm')
                                ->numeric()->minValue(0)->live(onBlur: true)
                                ->columnSpan(['default' => 1, 'sm' => 1, 'md' => 3])
                                ->visible(fn (callable $get) => $get('input_mode') === 'rect'),

                            TextInput::make('piece_diameter_mm')
                                ->label('Diameter Ø')->suffix('mm')
                                ->numeric()->minValue(0)->live(onBlur: true)
                                ->columnSpan(['default' => 1, 'sm' => 1, 'md' => 3])
                                ->visible(fn (callable $get) => $get('input_mode') === 'circle'),

                            TextInput::make('qty')
                                ->label('Jumlah')
                                ->numeric()->minValue(0)->default(1)->live(onBlur: true)
                                ->columnSpan(['default' => 1, 'sm' => 1, 'md' => 3])
                                ->visible(fn (callable $get) => $get('input_mode') === 'direct')
                                ->suffix(fn (callable $get) => Material::find($get('material_id'))?->baseUnit()),

                            TextInput::make('piece_count')
                                ->label('Berapa potong')
                                ->numeric()->minValue(0)->default(1)->live(onBlur: true)
                                ->columnSpan(['default' => 1, 'sm' => 1, 'md' => 2])
                                ->visible(fn (callable $get) => $get('input_mode') !== 'direct'),

                            TextInput::make('waste_percent')
                                ->label('Susut')->suffix('%')
                                ->numeric()->default(0)->minValue(0)->live(onBlur: true)
                                ->columnSpan(['default' => 1, 'sm' => 1, 'md' => 2])
                                ->helperText('gagal / ulang'),

                            Toggle::make('use_nesting')
                                ->label('Hitung sisa potong')
                                ->default(true)
                                ->live()
                                ->inline(false)
                                ->columnSpan(['default' => 1, 'sm' => 1, 'md' => 3])
                                ->visible(fn (callable $get) => Material::find($get('material_id'))?->dimension_type === 'sheet'
                                    && in_array($get('input_mode'), ['rect', 'circle'], true))
                                ->helperText('Matikan bila diambil dari sisa plat.'),

                            // Hasil hitung ditampilkan langsung agar salah ketik
                            // ketahuan sebelum disimpan.
                            Placeholder::make('hasil')
                                ->label('Kebutuhan & biaya')
                                ->columnSpan(self::SPAN_PENUH)
                                ->content(fn (callable $get) => self::preview($get)),

                            TextInput::make('notes')->label('Catatan')
                                ->columnSpan(self::SPAN_PENUH)->maxLength(255),
                        ]),
                ]),
        ];
    }

    // -------------------------------------------------------- proses & mesin

    /** @return array<int, mixed> */
    private static function prosesDanMesin(): array
    {
        return [
            Section::make('Proses Kerja')
                ->description('Tenaga kerja dihitung dari waktu: isi berapa menit tiap proses, tarif per jamnya diambil dari master Komponen Biaya. Proses borongan cukup diisi jumlahnya.')
                ->schema([
                    Repeater::make('costs')
                        ->relationship()
                        ->hiddenLabel()
                        ->addActionLabel('Tambah proses')
                        ->orderColumn('sort_order')
                        ->collapsible()
                        ->itemLabel(fn (array $state) => self::costLabel($state))
                        ->columns(['default' => 1, 'sm' => 2, 'md' => 12])
                        ->schema([
                            Select::make('cost_component_id')
                                ->label('Proses')
                                ->options(fn () => CostComponent::where('is_active', true)
                                    ->orderBy('sort_order')->get()
                                    ->mapWithKeys(fn (CostComponent $c) => [$c->id => $c->name.' — '.$c->displayRate()]))
                                ->searchable()->required()
                                ->columnSpan(['default' => 1, 'sm' => 2, 'md' => 5])
                                ->live()
                                ->afterStateUpdated(function ($state, callable $set) {
                                    // Menit standar dari master jadi titik awal;
                                    // tetap bisa diubah per formula.
                                    $c = CostComponent::find($state);
                                    if ($c?->isHourly()) {
                                        $set('minutes', (float) $c->default_minutes);
                                        $set('qty', 1);
                                    } else {
                                        $set('minutes', 0);
                                    }
                                }),

                            Select::make('bom_group')
                                ->label('Bagian')
                                ->options(FormulaMaterial::BOM_GROUPS)
                                ->default('finishing')
                                ->required()
                                ->columnSpan(['default' => 1, 'sm' => 1, 'md' => 3]),

                            TextInput::make('minutes')
                                ->label('Waktu')->suffix('menit')
                                ->numeric()->minValue(0)->default(0)->live(onBlur: true)
                                ->columnSpan(['default' => 1, 'sm' => 1, 'md' => 2])
                                ->visible(fn (callable $get) => CostComponent::find($get('cost_component_id'))?->isHourly() ?? true),

                            TextInput::make('qty')
                                ->label('Jumlah')
                                ->numeric()->required()->default(1)->minValue(0)->live(onBlur: true)
                                ->columnSpan(['default' => 1, 'sm' => 1, 'md' => 2])
                                ->visible(fn (callable $get) => ! (CostComponent::find($get('cost_component_id'))?->isHourly() ?? true)),

                            Placeholder::make('biaya')
                                ->label('Biaya')
                                ->columnSpan(['default' => 1, 'sm' => 1, 'md' => 2])
                                ->content(function (callable $get) {
                                    $c = CostComponent::find($get('cost_component_id'));

                                    if (! $c) {
                                        return '—';
                                    }

                                    return 'Rp '.number_format(
                                        $c->costForMinutes((float) $get('minutes'), (float) ($get('qty') ?: 1)),
                                        0, ',', '.'
                                    );
                                }),

                            TextInput::make('notes')->label('Catatan')
                                ->columnSpan(self::SPAN_PENUH)->maxLength(255),
                        ]),
                ]),

            Section::make('Pemakaian Mesin')
                ->description('Isi berapa menit tiap mesin menyala. Biaya per jamnya — penyusutan, listrik, dan maintenance — sudah dihitung di master Mesin.')
                ->collapsed()
                ->schema([
                    Repeater::make('machines')
                        ->relationship()
                        ->hiddenLabel()
                        ->addActionLabel('Tambah pemakaian mesin')
                        ->orderColumn('sort_order')
                        ->itemLabel(fn (array $state) => self::machineLabel($state))
                        ->columns(['default' => 1, 'sm' => 2, 'md' => 12])
                        ->schema([
                            Select::make('machine_id')
                                ->label('Mesin')
                                ->options(fn () => Machine::where('is_active', true)
                                    ->orderBy('sort_order')->get()
                                    ->mapWithKeys(fn (Machine $m) => [$m->id => $m->name.' — '.$m->displayHourlyCost()]))
                                ->searchable()->required()
                                ->columnSpan(['default' => 1, 'sm' => 2, 'md' => 5])
                                ->live(),

                            Select::make('bom_group')
                                ->label('Bagian')
                                ->options(FormulaMaterial::BOM_GROUPS)
                                ->default('other')
                                ->required()
                                ->columnSpan(['default' => 1, 'sm' => 1, 'md' => 3]),

                            TextInput::make('minutes')
                                ->label('Waktu')->suffix('menit')
                                ->numeric()->required()->minValue(0)->default(0)->live(onBlur: true)
                                ->columnSpan(['default' => 1, 'sm' => 1, 'md' => 2]),

                            Placeholder::make('biaya')
                                ->label('Biaya')
                                ->columnSpan(['default' => 1, 'sm' => 1, 'md' => 2])
                                ->content(function (callable $get) {
                                    $m = Machine::find($get('machine_id'));

                                    return $m
                                        ? 'Rp '.number_format($m->costForMinutes((float) $get('minutes')), 0, ',', '.')
                                        : '—';
                                }),

                            TextInput::make('notes')->label('Catatan')
                                ->columnSpan(self::SPAN_PENUH)->maxLength(255),
                        ]),
                ]),
        ];
    }

    // ------------------------------------------------------------------ hasil

    /** @return array<int, mixed> */
    private static function hasil(): array
    {
        return [
            Section::make('Perhitungan')
                ->description('Angka di bawah dihitung dari data yang tersimpan. Simpan dulu bila baru saja mengubah isian.')
                ->schema([
                    Placeholder::make('kartu_hpp')
                        ->hiddenLabel()
                        ->columnSpanFull()
                        ->content(fn (?Formula $record) => $record
                            ? view('filament.resources.formulas.kartu-hpp', ['formula' => $record->fresh([
                                'materials.material', 'costs.component', 'machines.machine',
                            ])])
                            : 'Simpan formula ini dulu untuk melihat perhitungannya.'),
                ]),
        ];
    }

    // --------------------------------------------------------------- pembantu

    /** Judul ringkas tiap baris repeater bahan. */
    private static function itemLabel(array $state): string
    {
        $m = Material::find($state['material_id'] ?? null);

        if (! $m) {
            return 'Bahan baru';
        }

        $grup = FormulaMaterial::BOM_GROUPS[$state['bom_group'] ?? 'other'] ?? '';

        return trim($grup.' — '.$m->name);
    }

    /** Judul baris proses kerja. */
    private static function costLabel(array $state): string
    {
        $c = CostComponent::find($state['cost_component_id'] ?? null);

        if (! $c) {
            return 'Proses baru';
        }

        $grup = FormulaMaterial::BOM_GROUPS[$state['bom_group'] ?? 'other'] ?? '';
        $ukuran = $c->isHourly()
            ? rtrim(rtrim(number_format((float) ($state['minutes'] ?? 0), 2, ',', '.'), '0'), ',').' menit'
            : rtrim(rtrim(number_format((float) ($state['qty'] ?? 0), 2, ',', '.'), '0'), ',').' x';

        return trim($grup.' — '.$c->name.' ('.$ukuran.')');
    }

    /** Judul baris pemakaian mesin. */
    private static function machineLabel(array $state): string
    {
        $m = Machine::find($state['machine_id'] ?? null);

        if (! $m) {
            return 'Mesin baru';
        }

        $menit = rtrim(rtrim(number_format((float) ($state['minutes'] ?? 0), 2, ',', '.'), '0'), ',');

        return $m->name.' ('.$menit.' menit)';
    }

    /** Pratinjau kebutuhan & biaya untuk baris yang sedang diisi. */
    private static function preview(callable $get): string
    {
        $m = Material::find($get('material_id'));

        if (! $m) {
            return 'Pilih bahan dulu.';
        }

        $line = new FormulaMaterial([
            'input_mode' => $get('input_mode') ?: 'direct',
            'piece_length_mm' => $get('piece_length_mm'),
            'piece_width_mm' => $get('piece_width_mm'),
            'piece_diameter_mm' => $get('piece_diameter_mm'),
            'piece_count' => $get('piece_count') ?: 1,
            'qty' => $get('qty') ?: 0,
            'waste_percent' => $get('waste_percent') ?: 0,
            'use_nesting' => (bool) ($get('use_nesting') ?? true),
        ]);
        $line->setRelation('material', $m);

        $qty = $line->input_mode === 'direct' ? (float) $line->qty : $line->computeQty();
        $line->qty = $qty;

        $ringkas = sprintf(
            '%s  →  + susut %s%%  =  %s  ·  %s  =  Rp %s',
            $m->formatBase($qty),
            rtrim(rtrim(number_format((float) $line->waste_percent, 2, ',', '.'), '0'), ','),
            $m->formatBase($line->effectiveQty()),
            $m->displayBasePrice(),
            number_format($line->subtotal(), 0, ',', '.')
        );

        // Untuk plat, jelaskan dari mana angka jatah lembarannya datang.
        if ($n = $line->nesting()) {
            $ringkas .= "\n".$m->nestingLabel(...array_merge($line->pieceBox(), [
                null, $qty / max((float) $line->piece_count, 1),
            ]))
                .sprintf(' · jatah %s per potong · rugi sisa potong Rp %s',
                    $m->formatBase($n['area_per_potong']),
                    number_format($line->nestingWasteCost(), 0, ',', '.'));
        }

        return $ringkas;
    }
}
