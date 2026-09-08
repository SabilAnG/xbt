<?php

namespace App\Filament\Resources\Productions\Schemas;

use App\Models\CostComponent;
use App\Models\Formula;
use App\Models\Machine;
use App\Models\Material;
use App\Models\Production;
use App\Services\DocumentNumber;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProductionForm
{
    public static function configure(Schema $schema): Schema
    {
        $terkunci = fn (?Production $record) => $record?->isPosted();

        return $schema->components([
            Section::make('Nota Produksi')
                ->description('Pilih mau bikin knalpot apa, lalu isi mau buat berapa biji. Kebutuhan bahannya muncul sendiri di bawah.')
                ->columns(['default' => 1, 'md' => 6])
                ->schema([
                    TextInput::make('production_number')
                        ->label('No. Produksi')
                        ->required()->unique(ignoreRecord: true)
                        ->default(fn () => DocumentNumber::next('productions'))
                        ->disabled($terkunci)->dehydrated()
                        ->columnSpan(['default' => 1, 'md' => 2]),

                    DatePicker::make('produced_at')
                        ->label('Tanggal')->required()->default(now())->disabled($terkunci)
                        ->columnSpan(['default' => 1, 'md' => 2]),

                    Select::make('warehouse_id')
                        ->label('Gudang')
                        ->relationship('warehouse', 'name')
                        ->searchable()->preload()->disabled($terkunci)
                        ->columnSpan(['default' => 1, 'md' => 2]),

                    Select::make('formula_id')
                        ->label('Mau bikin knalpot apa')
                        ->options(fn () => Formula::where('is_active', true)
                            ->with('motorcycleModel')->get()
                            ->mapWithKeys(fn (Formula $f) => [
                                $f->id => $f->name.($f->motorcycleModel ? ' — '.$f->motorcycleModel->name : ''),
                            ]))
                        ->searchable()->required()
                        ->columnSpan(['default' => 1, 'md' => 3])
                        ->disabled($terkunci)
                        ->live(),

                    // Yang diketik pengguna adalah jumlah knalpot, bukan jumlah
                    // resep. Berapa kali resep dijalankan diturunkan dari sini.
                    TextInput::make('target_unit')
                        ->label('Mau buat berapa')
                        ->numeric()->minValue(1)->default(1)
                        ->columnSpan(['default' => 1, 'md' => 2])
                        ->disabled($terkunci)
                        ->dehydrated(false)
                        ->live(onBlur: true)
                        ->suffix(fn (callable $get) => Formula::find($get('formula_id'))?->output_unit ?? 'unit')
                        ->afterStateHydrated(function (?Production $record, callable $set) {
                            if (! $record?->exists) {
                                return;
                            }

                            // Nota yang belum diisi dari formula belum punya
                            // output_qty, jadi dihitung dari jumlah resepnya.
                            $unit = (float) $record->output_qty
                                ?: (float) $record->batch_qty * (float) ($record->formula->output_qty ?? 1);

                            $set('target_unit', $unit ?: 1);
                        })
                        ->afterStateUpdated(function ($state, callable $get, callable $set) {
                            $f = Formula::find($get('formula_id'));
                            $set('batch_qty', $f ? $f->batchFor((float) $state) : (float) $state);
                        }),

                    Placeholder::make('info_resep')
                        ->label('Jalan resep')
                        ->columnSpan(['default' => 1, 'md' => 1])
                        ->content(function (callable $get) {
                            $f = Formula::find($get('formula_id'));

                            if (! $f) {
                                return '—';
                            }

                            $batch = $f->batchFor((float) ($get('target_unit') ?: 0));

                            return rtrim(rtrim(number_format($batch, 2, ',', '.'), '0'), ',').'x';
                        }),

                    Hidden::make('batch_qty')->default(1),
                ]),

            Section::make('Cek Kebutuhan & Stok')
                ->description('Dihitung langsung dari formula. Kalau ada bahan yang kurang, di sini akan muncul peringatannya berikut berapa yang harus dibeli.')
                ->visible(fn (?Production $record) => ! $record?->isPosted())
                ->schema([
                    Placeholder::make('cek_kebutuhan')
                        ->hiddenLabel()
                        ->columnSpanFull()
                        ->content(fn (callable $get) => view(
                            'filament.resources.productions.cek-kebutuhan',
                            [
                                'formula' => Formula::with('materials.material')->find($get('formula_id')),
                                'target' => (float) ($get('target_unit') ?: 0),
                            ]
                        )),
                ]),

            Section::make('Bahan Dipakai')
                ->description('Terisi dari formula, tapi boleh disesuaikan bila pemakaian nyata berbeda.')
                ->schema([
                    Repeater::make('materials')
                        ->relationship()
                        ->hiddenLabel()
                        ->addActionLabel('Tambah bahan')
                        ->columns(4)
                        ->disabled($terkunci)
                        ->schema([
                            Select::make('material_id')
                                ->label('Bahan')
                                ->options(fn () => Material::where('is_active', true)->get()
                                    ->mapWithKeys(fn (Material $m) => [
                                        $m->id => sprintf('%s (stok %s)', $m->name, $m->displayStock()),
                                    ]))
                                ->searchable()->required()->columnSpan(2),

                            TextInput::make('qty')->label('Jumlah')
                                ->numeric()->required()->default(0)->minValue(0)
                                ->helperText(fn (callable $get) => 'satuan: '
                                    .(Material::find($get('material_id'))?->baseUnit() ?? '-')),

                            TextInput::make('unit_cost')->label('Harga Satuan')
                                ->numeric()->default(0)->prefix('Rp')
                                ->helperText('Dibekukan saat dibukukan.'),
                        ]),
                ]),

            Section::make('Proses Kerja')
                ->description('Menit boleh disesuaikan bila pengerjaan nyata lebih lama atau lebih cepat dari resep.')
                ->schema([
                    Repeater::make('costs')
                        ->relationship()
                        ->hiddenLabel()
                        ->addActionLabel('Tambah proses')
                        ->columns(4)
                        ->disabled($terkunci)
                        ->schema([
                            Select::make('cost_component_id')
                                ->label('Proses')
                                ->options(fn () => CostComponent::where('is_active', true)->get()
                                    ->mapWithKeys(fn (CostComponent $c) => [$c->id => $c->name.' — '.$c->displayRate()]))
                                ->searchable()->required()->columnSpan(2)
                                ->live()
                                ->afterStateUpdated(function ($state, callable $set) {
                                    $c = CostComponent::find($state);
                                    $set('rate_type', $c?->rate_type ?? 'per_unit');

                                    if ($c?->isHourly()) {
                                        $set('minutes', (float) $c->default_minutes);
                                        $set('qty', 1);
                                    }
                                }),

                            Hidden::make('rate_type')->default('per_unit'),

                            TextInput::make('minutes')->label('Menit')
                                ->numeric()->default(0)->minValue(0)
                                ->visible(fn (callable $get) => CostComponent::find($get('cost_component_id'))?->isHourly() ?? true),

                            TextInput::make('qty')->label('Jumlah')
                                ->numeric()->required()->default(1)->minValue(0)
                                ->visible(fn (callable $get) => ! (CostComponent::find($get('cost_component_id'))?->isHourly() ?? true)),
                        ]),
                ]),

            Section::make('Pemakaian Mesin')
                ->description('Biaya per jam mesin — penyusutan, listrik, maintenance — diambil dari master Mesin.')
                ->collapsed()
                ->schema([
                    Repeater::make('machines')
                        ->relationship()
                        ->hiddenLabel()
                        ->addActionLabel('Tambah mesin')
                        ->columns(3)
                        ->disabled($terkunci)
                        ->schema([
                            Select::make('machine_id')
                                ->label('Mesin')
                                ->options(fn () => Machine::where('is_active', true)->get()
                                    ->mapWithKeys(fn (Machine $m) => [$m->id => $m->name.' — '.$m->displayHourlyCost()]))
                                ->searchable()->required()->columnSpan(2),

                            TextInput::make('minutes')->label('Menit')
                                ->numeric()->required()->default(0)->minValue(0),
                        ]),
                ]),

            Section::make('Hasil Perhitungan')
                ->description('Terisi setelah nota dibukukan.')
                ->columns(4)
                ->visible(fn (?Production $r) => $r?->isPosted())
                ->schema([
                    TextInput::make('material_cost')->label('Biaya Bahan')->prefix('Rp')->disabled(),
                    TextInput::make('service_cost')->label('Tenaga Kerja')->prefix('Rp')->disabled(),
                    TextInput::make('machine_cost')->label('Biaya Mesin')->prefix('Rp')->disabled(),
                    TextInput::make('overhead_cost')->label('Overhead')->prefix('Rp')->disabled(),
                    TextInput::make('total_minutes')->label('Total Menit Kerja')->suffix('menit')->disabled(),
                    TextInput::make('total_cost')->label('Total')->prefix('Rp')->disabled(),
                    TextInput::make('output_qty')->label('Unit Dihasilkan')->disabled(),
                    TextInput::make('hpp_per_unit')->label('HPP per Unit')->prefix('Rp')->disabled(),
                ]),

            Section::make('Catatan')
                ->collapsed()
                ->schema([Textarea::make('notes')->hiddenLabel()->rows(3)]),
        ]);
    }
}
