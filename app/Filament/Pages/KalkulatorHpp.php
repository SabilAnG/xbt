<?php

namespace App\Filament\Pages;

use App\Models\Formula;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Kalkulator HPP & kapasitas produksi.
 *
 * Menjawab dua pertanyaan sehari-hari:
 *   1. Modal satu knalpot berapa?
 *   2. Dengan stok bahan sekarang, bisa jadi berapa biji?
 *
 * Semua angka dihitung langsung dari master bahan dan komponen biaya, jadi
 * halaman ini tidak menyimpan apa pun — hanya membaca.
 */
class KalkulatorHpp extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalculator;

    protected static ?string $navigationLabel = 'Kalkulator HPP';

    protected static ?string $title = 'Kalkulator HPP & Kapasitas';

    protected static ?int $navigationSort = 50;

    protected string $view = 'filament.pages.kalkulator-hpp';

    /** @var array<string, mixed> */
    public array $data = [];

    public static function getNavigationGroup(): ?string
    {
        return 'Produksi';
    }

    public function mount(): void
    {
        $this->form->fill([
            'formula_id' => Formula::where('is_active', true)->value('id'),
            'target_unit' => 10,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Pilih Formula')
                    ->columns(2)
                    ->schema([
                        Select::make('formula_id')
                            ->label('Formula')
                            ->options(fn () => Formula::where('is_active', true)->pluck('name', 'id'))
                            ->searchable()
                            ->live()
                            ->required(),

                        TextInput::make('target_unit')
                            ->label('Simulasi: mau buat berapa unit?')
                            ->numeric()
                            ->minValue(1)
                            ->default(10)
                            ->live(onBlur: true)
                            ->helperText('Untuk melihat kebutuhan bahan dan total biayanya.'),
                    ]),
            ])
            ->statePath('data');
    }

    public function getFormulaProperty(): ?Formula
    {
        $id = $this->data['formula_id'] ?? null;

        return $id
            ? Formula::with(['materials.material', 'costs.component', 'machines.machine'])->find($id)
            : null;
    }

    /**
     * Kebutuhan bahan untuk sejumlah unit target, plus penilaian cukup/kurang.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getSimulasiProperty(): array
    {
        $formula = $this->formula;

        if (! $formula) {
            return [];
        }

        $target = max((float) ($this->data['target_unit'] ?? 0), 0);

        // Rumusnya tidak ditulis ulang di sini — dipakai bersama form nota
        // produksi lewat Formula::requirementFor(), supaya angka di kedua
        // layar tidak mungkin berbeda.
        return array_map(
            fn (array $r) => $r + ['bahan' => $r['material']?->name ?? '-'],
            $formula->requirementFor($target)['baris']
        );
    }

    /**
     * Ke mana bahan yang dibeli itu pergi: menempel di produk, hilang jadi
     * susut, atau tersisa. Rumusnya dipakai bersama lewat Formula::wasteFor().
     *
     * @return array<string, mixed>
     */
    public function getSisaProperty(): array
    {
        $formula = $this->formula;

        if (! $formula) {
            return ['baris' => [], 'rp_susut' => 0, 'rp_sisa_berguna' => 0, 'rp_sisa_terbuang' => 0, 'rp_sampah' => 0];
        }

        return $formula->wasteFor(max((float) ($this->data['target_unit'] ?? 0), 0));
    }

    /**
     * Total biaya untuk target simulasi: bahan + kerja + mesin + overhead.
     *
     * @return array<string, float>
     */
    public function getTotalSimulasiProperty(): array
    {
        $formula = $this->formula;

        if (! $formula) {
            return ['bahan' => 0, 'jasa' => 0, 'mesin' => 0, 'overhead' => 0, 'total' => 0];
        }

        $batch = $this->batch;

        return [
            'bahan' => collect($this->simulasi)->sum('biaya'),
            'jasa' => $formula->serviceCost() * $batch,
            'mesin' => $formula->machineCost() * $batch,
            'overhead' => $formula->overheadCost() * $batch,
            'total' => $formula->totalCost() * $batch,
        ];
    }

    public function getBatchProperty(): float
    {
        $formula = $this->formula;

        if (! $formula) {
            return 0;
        }

        $target = max((float) ($this->data['target_unit'] ?? 0), 0);

        return ceil($target / max((float) $formula->output_qty, 0.0001));
    }
}
