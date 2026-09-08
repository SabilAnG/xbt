<?php

namespace App\Filament\Pages;

use App\Models\Machine;
use App\Models\Material;
use App\Models\OverheadItem;
use App\Models\Setting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Angka-angka yang dipakai bersama oleh semua formula.
 *
 * Ditaruh di satu tempat supaya kenaikan tarif listrik cukup diubah sekali,
 * bukan diketik ulang di tiap mesin.
 */
class PengaturanProduksi extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static ?string $navigationLabel = 'Pengaturan Produksi';

    protected static ?string $title = 'Pengaturan Produksi';

    protected static ?int $navigationSort = 90;

    protected string $view = 'filament.pages.pengaturan-produksi';

    /** Kunci setelan beserta nilai bawaannya. */
    private const KEYS = [
        'produksi.tarif_listrik' => Machine::DEFAULT_TARIF_LISTRIK,
        'produksi.target_produksi_bulanan' => OverheadItem::DEFAULT_TARGET,
        'produksi.pembulatan_harga' => 1000,
        'produksi.kerf_mm' => Material::DEFAULT_KERF,
    ];

    /** @var array<string, mixed> */
    public array $data = [];

    public static function getNavigationGroup(): ?string
    {
        return 'Master Produksi';
    }

    public function mount(): void
    {
        $values = [];

        foreach (self::KEYS as $key => $default) {
            $values[str_replace('.', '_', $key)] = Setting::get($key, $default);
        }

        $this->form->fill($values);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Listrik')
                    ->description('Dipakai untuk menghitung biaya listrik tiap mesin. Listrik masuk lewat mesin — bukan pos terpisah — agar tidak terhitung dua kali.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('produksi_tarif_listrik')
                            ->label('Tarif Listrik per kWh')
                            ->numeric()->required()->minValue(0)->prefix('Rp')
                            ->helperText('Lihat tagihan PLN: total tagihan dibagi jumlah kWh terpakai.'),
                    ]),

                Section::make('Pemotongan Plat')
                    ->description('Plat dihitung dengan nesting: yang dibebankan bukan luas potongannya, melainkan jatah lembaran per potongan. Sisa lembaran yang tidak terpakai tetap uang yang sudah keluar.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('produksi_kerf_mm')
                            ->label('Lebar Mata Potong (kerf)')
                            ->numeric()->required()->minValue(0)->step(0.5)->suffix('mm')
                            ->helperText('Material yang hilang jadi serbuk tiap kali memotong. Gerinda potong sekitar 3 mm, plasma 1–2 mm.'),
                    ]),

                Section::make('Overhead')
                    ->description('Biaya tetap bulanan dibagi rata ke tiap knalpot yang jadi. Makin banyak yang selesai, makin ringan beban tiap unit.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('produksi_target_produksi_bulanan')
                            ->label('Target Produksi per Bulan')
                            ->numeric()->required()->minValue(1)->suffix('unit')
                            ->live(onBlur: true)
                            ->helperText('Pakai angka realistis, bukan angka harapan — target ketinggian membuat HPP terlihat murah padahal tidak.'),

                        Placeholder::make('beban_overhead')
                            ->label('Beban overhead per unit')
                            ->content(function (callable $get) {
                                $total = OverheadItem::monthlyTotal();
                                $target = max((float) ($get('produksi_target_produksi_bulanan') ?: 0), 1);
                                $rp = fn ($n) => 'Rp '.number_format($n, 0, ',', '.');

                                return sprintf('%s ÷ %s unit = %s per knalpot',
                                    $rp($total),
                                    rtrim(rtrim((string) $target, '0'), '.'),
                                    $rp($total / $target));
                            }),
                    ]),

                Section::make('Harga Jual')
                    ->description('Harga hasil hitungan dibulatkan ke atas agar enak dibaca di daftar harga.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('produksi_pembulatan_harga')
                            ->label('Pembulatan Harga')
                            ->numeric()->required()->minValue(0)->prefix('Rp')
                            ->helperText('1000 membulatkan Rp439.100 jadi Rp440.000. Isi 0 untuk tanpa pembulatan.'),
                    ]),
            ])
            ->statePath('data');
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')->label('Simpan')->submit('save'),
        ];
    }

    public function save(): void
    {
        foreach ($this->form->getState() as $field => $value) {
            // Hanya garis bawah pertama yang memisahkan grup dari nama, jadi
            // "produksi_tarif_listrik" jadi "produksi.tarif_listrik".
            Setting::put(preg_replace('/_/', '.', $field, 1), $value);
        }

        Notification::make()->success()->title('Pengaturan disimpan')->send();
    }
}
