<?php

namespace App\Filament\Resources\Formulas\Schemas;

use App\Filament\Resources\ProductionItems\Schemas\ProductionItemForm;
use App\Models\ExhaustComponent;
use App\Models\FormulaLine;
use App\Models\MotorcycleModel;
use App\Models\ProductionItem;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Form resep knalpot.
 *
 * Barisnya adalah daftar komponen, dan formula baru langsung terisi seluruh
 * komponen yang ada — karena membuat knalpot memang dimulai dengan menentukan
 * bagian-bagiannya, bukan dengan halaman kosong. Yang tidak dipakai tinggal
 * dihapus barisnya.
 */
class FormulaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Formula')
                ->columns(3)
                ->schema([
                    TextInput::make('name')
                        ->label('Nama Formula')->required()->maxLength(255)
                        ->helperText('Contoh: Racing Standar, Full System.'),

                    Select::make('motorcycle_model_id')
                        ->label('Type Motor')
                        ->options(fn () => MotorcycleModel::query()
                            ->with('brand')->where('is_active', true)
                            ->get()->mapWithKeys(fn (MotorcycleModel $m) => [$m->id => $m->fullName()]))
                        ->searchable()
                        ->helperText('Diambil dari master motor. Boleh dikosongkan untuk resep yang dipakai lintas motor.'),

                    TextInput::make('code')
                        ->label('Kode')->required()->maxLength(64)
                        ->unique(ignoreRecord: true)
                        ->helperText('Contoh: RS-MIO.'),

                    TextInput::make('output_qty')
                        ->label('Sekali resep menghasilkan')
                        ->numeric()->minValue(0.001)->default(1)->required(),

                    TextInput::make('output_unit')
                        ->label('Satuan hasil')->default('set')->required()->maxLength(255)
                        ->helperText('set, pcs, unit.'),

                    Toggle::make('is_active')->label('Aktif')->default(true),
                ]),

            Section::make('Komponen')
                ->description('Tiap komponen ditentukan bahannya dan berapa ukurannya. Kebutuhan dalam satuan pakai dihitung sendiri — Anda cukup menyebut berapa cm.')
                ->schema([
                    Repeater::make('lines')
                        ->relationship()
                        ->hiddenLabel()
                        ->addActionLabel('Tambah komponen')
                        ->orderColumn('sort_order')
                        ->collapsible()
                        ->collapseAllAction(fn ($action) => $action->label('Tutup semua'))
                        ->itemLabel(fn (array $state) => self::barisLabel($state))
                        ->default(fn () => self::seluruhKomponen())
                        ->columns(['default' => 1, 'md' => 12])
                        ->schema([
                            Select::make('exhaust_component_id')
                                ->label('Komponen')
                                ->options(fn () => self::pilihanKomponen())
                                ->searchable()->required()->distinct()
                                ->columnSpan(['default' => 1, 'md' => 3]),

                            Select::make('production_item_id')
                                ->label('Bahan')
                                ->options(fn () => ProductionItem::query()
                                    ->where('is_active', true)->orderBy('name')->pluck('name', 'id'))
                                ->searchable()->live()
                                ->columnSpan(['default' => 1, 'md' => 3])
                                ->placeholder('Belum ditentukan')
                                ->createOptionForm(fn () => ProductionItemForm::ringkas())
                                ->createOptionUsing(fn (array $data) => ProductionItem::create($data)->getKey())
                                ->afterStateUpdated(function ($state, callable $set) {
                                    // Cara mengisi ukuran mengikuti bentuk bahannya.
                                    $barang = ProductionItem::find($state);

                                    $set('input_mode', match ($barang?->shape) {
                                        'linear' => 'length',
                                        'sheet' => 'rect',
                                        default => 'count',
                                    });
                                }),

                            Select::make('input_mode')
                                ->label('Cara isi')
                                ->options(fn (callable $get) => FormulaLine::modesFor(
                                    ProductionItem::find($get('production_item_id'))?->shape
                                ))
                                ->default('count')->required()->live()
                                ->columnSpan(['default' => 1, 'md' => 2]),

                            Select::make('size_unit')
                                ->label('Satuan')
                                ->options(ProductionItem::SIZE_UNIT_LABELS)
                                ->default('cm')->required()->live()
                                ->columnSpan(['default' => 1, 'md' => 2])
                                ->visible(fn (callable $get) => $get('input_mode') !== 'count'),

                            TextInput::make('piece_length_mm')
                                ->label('Panjang')
                                ->numeric()->minValue(0)->live(onBlur: true)
                                ->suffix(fn (callable $get) => self::lambang($get('size_unit')))
                                ->formatStateUsing(fn ($state, callable $get) => self::keSatuan($state, $get('size_unit')))
                                ->dehydrateStateUsing(fn ($state, callable $get) => self::keMm($state, $get('size_unit')))
                                ->required(fn (callable $get) => $get('input_mode') !== 'count')
                                ->visible(fn (callable $get) => $get('input_mode') !== 'count')
                                ->columnSpan(['default' => 1, 'md' => 1]),

                            TextInput::make('piece_width_mm')
                                ->label('Lebar')
                                ->numeric()->minValue(0)->live(onBlur: true)
                                ->suffix(fn (callable $get) => self::lambang($get('size_unit')))
                                ->formatStateUsing(fn ($state, callable $get) => self::keSatuan($state, $get('size_unit')))
                                ->dehydrateStateUsing(fn ($state, callable $get) => self::keMm($state, $get('size_unit')))
                                ->required(fn (callable $get) => $get('input_mode') === 'rect')
                                ->visible(fn (callable $get) => $get('input_mode') === 'rect')
                                ->columnSpan(['default' => 1, 'md' => 1]),

                            TextInput::make('piece_count')
                                ->label(fn (callable $get) => $get('input_mode') === 'count' ? 'Jumlah' : 'Berapa potong')
                                ->numeric()->minValue(0)->default(1)->required()->live(onBlur: true)
                                ->columnSpan(['default' => 1, 'md' => 2]),

                            Placeholder::make('kebutuhan')
                                ->label('Kebutuhan & biaya')
                                ->columnSpan(['default' => 1, 'md' => 4])
                                ->content(fn (callable $get) => self::previewBaris($get)),

                            TextInput::make('notes')
                                ->label('Catatan')->maxLength(255)
                                ->columnSpan(['default' => 1, 'md' => 6]),
                        ]),
                ]),

            Section::make('Catatan')
                ->collapsed()
                ->schema([Textarea::make('notes')->hiddenLabel()->rows(3)]),
        ]);
    }

    // ------------------------------------------------------------- pembantu

    /** "Header / P1" supaya tidak ambigu saat dipilih. */
    private static function pilihanKomponen(): array
    {
        return ExhaustComponent::query()
            ->komponen()->where('is_active', true)->with('parent')
            ->get()
            ->sortBy(fn (ExhaustComponent $k) => [$k->parent?->sort_order ?? 0, $k->sort_order])
            ->mapWithKeys(fn (ExhaustComponent $k) => [$k->id => $k->fullName()])
            ->all();
    }

    /**
     * Formula baru dimulai dengan seluruh komponen yang ada, bukan halaman
     * kosong — membuat knalpot memang dimulai dari menentukan bagiannya.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function seluruhKomponen(): array
    {
        return ExhaustComponent::query()
            ->komponen()->where('is_active', true)->with('parent')
            ->get()
            ->sortBy(fn (ExhaustComponent $k) => [$k->parent?->sort_order ?? 0, $k->sort_order])
            ->values()
            ->map(fn (ExhaustComponent $k, int $i) => [
                'exhaust_component_id' => $k->id,
                'input_mode' => 'count',
                'size_unit' => 'cm',
                'piece_count' => 1,
                'sort_order' => ($i + 1) * 10,
            ])
            ->all();
    }

    private static function lambang(?string $satuan): string
    {
        return $satuan === 'inch' ? '"' : ($satuan ?: 'mm');
    }

    private static function keSatuan(mixed $state, ?string $satuan): mixed
    {
        return ($state === null || $state === '')
            ? $state
            : round(ProductionItem::fromMm((float) $state, $satuan), 4);
    }

    private static function keMm(mixed $state, ?string $satuan): ?float
    {
        return ($state === null || $state === '')
            ? null
            : ProductionItem::toMm((float) $state, $satuan);
    }

    /** Kebutuhan dan biayanya, dihitung dari isian yang sedang diketik. */
    private static function previewBaris(callable $get): string
    {
        $barang = ProductionItem::find($get('production_item_id'));

        if (! $barang) {
            return 'Bahannya belum ditentukan.';
        }

        $baris = new FormulaLine([
            'input_mode' => $get('input_mode') ?: 'count',
            'size_unit' => $get('size_unit') ?: 'cm',
            'piece_length_mm' => self::keMm($get('piece_length_mm'), $get('size_unit')) ?: 0,
            'piece_width_mm' => self::keMm($get('piece_width_mm'), $get('size_unit')) ?: 0,
            'piece_count' => $get('piece_count') ?: 0,
        ]);

        $qty = $baris->computeQty();
        $biaya = $qty * $barang->basePrice();

        return $barang->formatBase($qty).'  ·  Rp '.number_format($biaya, 0, ',', '.');
    }

    private static function barisLabel(array $state): string
    {
        $komponen = ExhaustComponent::find($state['exhaust_component_id'] ?? null);

        if (! $komponen) {
            return 'Baris baru';
        }

        $barang = ProductionItem::find($state['production_item_id'] ?? null);

        return $komponen->fullName().' — '.($barang?->name ?? 'bahan belum ditentukan');
    }
}
