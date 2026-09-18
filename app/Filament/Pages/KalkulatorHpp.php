<?php

namespace App\Filament\Pages;

use App\Models\Formula;
use App\Support\HakPartner;
use App\Support\HitungHpp;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Components\Html;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;

/**
 * Menghitung HPP sebuah formula, lalu harga jualnya.
 *
 * Tidak menyimpan apa pun — ini alat hitung, bukan dokumen. Yang dicari orang
 * di sini selalu satu pertanyaan: "kalau saya buat sekian set knalpot Mio,
 * modalnya berapa dan harus dijual berapa?"
 *
 * Rinciannya sengaja ditampilkan utuh sampai ke tiap komponen. Angka HPP yang
 * muncul begitu saja tidak bisa diperiksa, dan yang tidak bisa diperiksa
 * akhirnya dipercaya begitu saja sampai ada yang rugi.
 */
class KalkulatorHpp extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalculator;

    protected static ?string $navigationLabel = 'Hitung Modal';

    protected static ?string $title = 'Hitung Modal';

    protected static ?int $navigationSort = 50;

    protected string $view = 'filament.pages.kalkulator-hpp';

    /** @var array<string, mixed> */
    public array $data = [];

    /** Disaring sama seperti resource: hak partner menentukan. */
    public static function canAccess(): bool
    {
        return HakPartner::boleh(static::class, 'Produksi');
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Produksi';
    }

    public function mount(): void
    {
        $this->form->fill([
            'formula_id' => Formula::where('is_active', true)->value('id'),
            'jumlah' => 1,
            'markup_persen' => 40,
            'markup_reseller_persen' => 20,
            'biaya_admin' => 0,
            'biaya_ongkir' => 0,
            'potongan_persen' => 10,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Yang dihitung')
                    ->description('Pilih resepnya, lalu sebut mau buat berapa.')
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        Select::make('formula_id')
                            ->label('Formula')
                            ->options(fn () => Formula::query()
                                ->with(['motorcycleModel.brand'])
                                ->where('is_active', true)
                                ->get()
                                ->mapWithKeys(fn (Formula $f) => [$f->id => $f->fullName()]))
                            ->searchable()->required()->live()
                            ->placeholder('Pilih formula'),

                        TextInput::make('jumlah')
                            ->label('Mau buat berapa')
                            ->numeric()->minValue(1)->default(1)->required()->live(onBlur: true)
                            ->suffix(fn (callable $get) => self::formula($get)?->output_unit ?? 'set')
                            ->helperText('Rincian per unit tetap ditampilkan, ini hanya mengalikan totalnya.'),
                    ]),

                Section::make('Rincian HPP')
                    ->description('Dari mana modalnya datang — bahan per komponen, lalu ongkos kerjanya.')
                    ->columnSpanFull()
                    ->schema([
                        Html::make(fn (callable $get) => new HtmlString(self::hitung($get)?->rincianHtml()
                            ?? '<p class="fi-color-gray">Pilih formula dulu.</p>')),
                    ]),

                Section::make('Harga Jual')
                    ->description('Markup dihitung dari HPP: 40% berarti harga jual = HPP + 40% HPP.')
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        TextInput::make('markup_persen')
                            ->label('Markup Umum')
                            ->numeric()->minValue(0)->default(40)->required()->suffix('%')
                            ->live(onBlur: true)
                            ->helperText('Untuk pembeli biasa.'),

                        TextInput::make('markup_reseller_persen')
                            ->label('Markup Reseller')
                            ->numeric()->minValue(0)->default(20)->required()->suffix('%')
                            ->live(onBlur: true)
                            ->helperText('Biasanya lebih kecil — reseller yang mengambil sisanya.'),

                        Html::make(fn (callable $get) => new HtmlString(self::hitung($get)?->hargaHtml()
                            ?? '<p class="fi-color-gray">Pilih formula dulu.</p>'))
                            ->columnSpanFull(),
                    ]),

                Section::make('Jual di Toko Online')
                    ->description('Marketplace memotong dari harga yang terpasang, jadi harganya harus dinaikkan lebih dulu supaya yang diterima tetap sama.')
                    ->columnSpanFull()
                    ->columns(3)
                    ->schema([
                        TextInput::make('biaya_admin')
                            ->label('Biaya Admin')
                            ->numeric()->minValue(0)->default(0)->prefix('Rp')
                            ->live(onBlur: true)
                            ->helperText('Per unit, kalau ada.'),

                        TextInput::make('biaya_ongkir')
                            ->label('Ongkir Ditanggung')
                            ->numeric()->minValue(0)->default(0)->prefix('Rp')
                            ->live(onBlur: true)
                            ->helperText('Isi 0 bila ongkir dibayar pembeli.'),

                        TextInput::make('potongan_persen')
                            ->label('Potongan Aplikasi')
                            ->numeric()->minValue(0)->maxValue(99)->default(10)->suffix('%')
                            ->live(onBlur: true)
                            ->helperText('Shopee/Tokopedia biasanya 8–12%.'),

                        Html::make(fn (callable $get) => new HtmlString(self::hitung($get)?->marketplaceHtml()
                            ?? '<p class="fi-color-gray">Pilih formula dulu.</p>'))
                            ->columnSpanFull(),
                    ]),
            ])
            ->statePath('data');
    }

    private static function formula(callable $get): ?Formula
    {
        return Formula::with(['lines.component.parent', 'lines.item', 'services.service'])
            ->find($get('formula_id'));
    }

    private static function hitung(callable $get): ?HitungHpp
    {
        $formula = self::formula($get);

        if (! $formula) {
            return null;
        }

        return new HitungHpp(
            formula: $formula,
            jumlah: max((float) ($get('jumlah') ?: 1), 1),
            markupPersen: (float) ($get('markup_persen') ?: 0),
            markupResellerPersen: (float) ($get('markup_reseller_persen') ?: 0),
            biayaAdmin: (float) ($get('biaya_admin') ?: 0),
            biayaOngkir: (float) ($get('biaya_ongkir') ?: 0),
            potonganPersen: min((float) ($get('potongan_persen') ?: 0), 99),
        );
    }
}
