<?php

namespace App\Filament\Resources\Formulas\Schemas;

use App\Models\ExhaustComponent;
use App\Models\FormulaLine;
use App\Models\MotorcycleModel;
use App\Models\ProductionItem;
use App\Models\ProductionService;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Html;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\FontWeight;
use Illuminate\Support\Collection;

/**
 * Form resep knalpot.
 *
 * Barisnya adalah daftar komponen, dan formula baru langsung terisi seluruh
 * komponen yang ada — karena membuat knalpot memang dimulai dengan menentukan
 * bagian-bagiannya, bukan dengan halaman kosong. Yang tidak dipakai tinggal
 * dihapus barisnya.
 *
 * Tampilannya tabel, bukan tumpukan blok: mengisi resep berarti menyusuri dua
 * puluhan komponen sekaligus, dan pekerjaan seperti itu dibaca menurun dalam
 * satu kolom, bukan dengan membuka-tutup satu per satu.
 */
class FormulaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            // Selebar halaman, bukan separuh: form resource bawaannya dua
            // kolom, dan di lebar segitu tabel komponen jatuh ke tampilan
            // bertumpuk — Filament baru menyusunnya sebagai tabel mulai 36rem.
            Section::make('Formula')
                ->description('Satu resep berlaku untuk satu type motor. Komponennya sama, yang berbeda ukurannya.')
                ->columnSpanFull()
                ->columns(3)
                ->schema([
                    TextInput::make('name')
                        ->label('Nama Formula')->required()->maxLength(255)
                        ->placeholder('Racing Standar')
                        ->helperText('Contoh: Racing Standar, Full System.'),

                    Select::make('motorcycle_model_id')
                        ->label('Type Motor')
                        ->options(fn () => MotorcycleModel::query()
                            ->with('brand')->where('is_active', true)
                            ->get()->mapWithKeys(fn (MotorcycleModel $m) => [$m->id => $m->fullName()]))
                        ->searchable()
                        ->placeholder('Lintas motor')
                        ->helperText('Dari master motor. Kosongkan bila resep ini dipakai lintas motor.'),

                    TextInput::make('code')
                        ->label('Kode')->required()->maxLength(64)
                        ->unique(ignoreRecord: true)
                        ->placeholder('RS-MIO')
                        ->helperText('Dipakai saat menyalin resep. Contoh: RS-MIO.'),

                    TextInput::make('output_qty')
                        ->label('Sekali resep menghasilkan')
                        ->numeric()->minValue(0.001)->default(1)->required()->live(onBlur: true)
                        ->helperText('Biasanya 1. Isi lebih bila sekali kerja jadi beberapa unit.'),

                    TextInput::make('output_unit')
                        ->label('Satuan hasil')->default('set')->required()->maxLength(255)
                        ->live(onBlur: true)
                        ->helperText('set, pcs, unit.'),

                    Toggle::make('is_active')
                        ->label('Aktif')->default(true)
                        ->helperText('Yang tidak aktif tidak ikut muncul saat menghitung produksi.'),
                ]),

            Section::make('Komponen')
                ->description('Pilih bahannya, lalu isi ukurannya. Barang beli jadi cukup jumlahnya; pipa dan plat minta ukuran potongan. Kebutuhan dan biayanya dihitung sendiri.')
                ->columnSpanFull()
                ->schema([
                    // Baris tempat bagian berganti diberi garis tegas, bukan
                    // garis tipis yang sama dengan baris lain — badge saja
                    // hanya menandai satu sel, sedangkan yang perlu terlihat
                    // adalah pemisah yang memotong seluruh lebar tabel.
                    Html::make(<<<'HTML'
                        <style>
                            .fi-fo-table-repeater tbody tr:has(.penanda-bagian) > td {
                                border-top: 2px solid var(--primary-500);
                            }
                            .fi-fo-table-repeater tbody tr:first-child:has(.penanda-bagian) > td {
                                border-top-width: 0;
                            }
                            /* Kotak setinggi nol ini tetap kebagian jarak grid
                               section; keluarkan dari susunannya. */
                            .fi-grid-col:has(> .fi-sc-component > style) {
                                display: none;
                            }
                        </style>
                        HTML),

                    Repeater::make('lines')
                        ->relationship()
                        ->hiddenLabel()
                        ->addActionLabel('Tambah komponen')
                        ->orderColumn('sort_order')
                        ->default(fn () => self::seluruhKomponen())
                        ->table([
                            // Nama bahan yang paling lebar — "PIPA 304 STAINLEES
                            // STEEL" — yang menentukan porsinya; kolom lain
                            // isinya angka pendek dan tidak butuh banyak.
                            TableColumn::make('Komponen')->width('16%')->markAsRequired(),
                            TableColumn::make('Bahan / Barang')->width('22%'),
                            TableColumn::make('Panjang')->width('10%')->alignment(Alignment::End),
                            TableColumn::make('Lebar')->width('10%')->alignment(Alignment::End),
                            TableColumn::make('Satuan')->width('12%'),
                            TableColumn::make('Jumlah')->width('6%')->alignment(Alignment::End),
                            TableColumn::make('Kebutuhan & Biaya')->width('17%')->alignment(Alignment::End),
                            TableColumn::make('Catatan')->width('7%'),
                        ])
                        ->schema([
                            // Penanda bagian ditumpuk di atas pilihan komponennya,
                            // bukan diberi kolom sendiri: yang dibutuhkan cuma
                            // tanda di tempat bagiannya berganti, dan kolom yang
                            // 22 dari 24 barisnya kosong itu pemborosan lebar.
                            Group::make([
                                Placeholder::make('bagian')
                                    ->hiddenLabel()
                                    ->badge()
                                    ->color('primary')
                                    ->extraAttributes(['class' => 'penanda-bagian'])
                                    ->content(fn (callable $get) => self::penandaBagian($get))
                                    ->visible(fn (callable $get) => filled(self::penandaBagian($get))),

                                // Tanpa tombol silang: komponennya wajib ada, jadi
                                // mengosongkannya bukan pilihan — dan lebar yang
                                // dimakan tombol itu lebih berguna untuk namanya.
                                Select::make('exhaust_component_id')
                                    ->hiddenLabel()
                                    ->options(fn () => self::pilihanKomponen())
                                    ->searchable()->required()->distinct()
                                    ->selectablePlaceholder(false)
                                    ->placeholder('Pilih komponen'),
                            ]),

                            // Namanya saja. Harga katalog pernah ikut di sini dan
                            // membuat tiap baris pipa pecah empat baris; kolom
                            // Kebutuhan & Biaya menjawabnya lebih baik, karena
                            // yang disebut di sana biaya ukuran ini — bukan
                            // harga sebatang utuh.
                            Select::make('production_item_id')
                                ->hiddenLabel()
                                ->options(fn () => ProductionItem::query()
                                    ->where('is_active', true)->orderBy('name')->pluck('name', 'id'))
                                ->searchable()->live()
                                ->placeholder('Belum ditentukan')
                                ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                    // Cara mengisi ukuran mengikuti bentuk bahannya, dan itu
                                    // bukan keputusan yang perlu diserahkan ke orang: pipa
                                    // selalu dipotong sepanjang sekian, plat selalu sekian
                                    // kali sekian, barang beli jadi selalu sekian buah.
                                    $barang = ProductionItem::find($state);

                                    $set('input_mode', match ($barang?->shape) {
                                        'linear' => 'length',
                                        'sheet' => 'rect',
                                        default => 'count',
                                    });

                                    if (blank($get('size_unit'))) {
                                        $set('size_unit', 'cm');
                                    }
                                }),

                            TextInput::make('piece_length_mm')
                                ->hiddenLabel()
                                ->numeric()->minValue(0)->live(onBlur: true)
                                ->placeholder('0')
                                ->formatStateUsing(fn ($state, callable $get) => self::keSatuan($state, $get('size_unit')))
                                ->dehydrateStateUsing(fn ($state, callable $get) => self::keMm($state, $get('size_unit')))
                                ->required(fn (callable $get) => $get('input_mode') !== 'count')
                                ->visible(fn (callable $get) => $get('input_mode') !== 'count'),

                            TextInput::make('piece_width_mm')
                                ->hiddenLabel()
                                ->numeric()->minValue(0)->live(onBlur: true)
                                ->placeholder('0')
                                ->formatStateUsing(fn ($state, callable $get) => self::keSatuan($state, $get('size_unit')))
                                ->dehydrateStateUsing(fn ($state, callable $get) => self::keMm($state, $get('size_unit')))
                                ->required(fn (callable $get) => $get('input_mode') === 'rect')
                                ->visible(fn (callable $get) => $get('input_mode') === 'rect'),

                            // Lambangnya saja. "Sentimeter (cm)" berguna di master
                            // barang, tapi di kolom setipis ini cuma pecah baris.
                            Select::make('size_unit')
                                ->hiddenLabel()
                                ->options(array_combine(
                                    array_keys(ProductionItem::SIZE_UNITS),
                                    array_keys(ProductionItem::SIZE_UNITS),
                                ))
                                ->selectablePlaceholder(false)
                                ->default('cm')->required()->live()
                                ->visible(fn (callable $get) => $get('input_mode') !== 'count'),

                            TextInput::make('piece_count')
                                ->hiddenLabel()
                                ->numeric()->minValue(0)->default(1)->required()->live(onBlur: true),

                            Placeholder::make('kebutuhan')
                                ->hiddenLabel()
                                ->weight(FontWeight::Medium)
                                ->content(fn (callable $get) => self::kebutuhanBaris($get))
                                ->color(fn (callable $get) => $get('production_item_id') ? null : 'gray'),

                            TextInput::make('notes')
                                ->hiddenLabel()->maxLength(255)
                                ->placeholder('—'),

                            // Turunan dari bentuk bahannya, bukan isian. Disimpan
                            // tersembunyi supaya baris lama tetap memakai caranya
                            // sendiri saat dibuka kembali.
                            Hidden::make('input_mode')->default('count'),
                        ]),

                    Placeholder::make('ringkasan')
                        ->hiddenLabel()
                        ->weight(FontWeight::Medium)
                        ->content(fn (callable $get) => self::ringkasan($get))
                        ->color(fn (callable $get) => self::adaYangKosong($get) ? 'warning' : 'success'),
                ]),

            // Setelah komponennya beres, barulah ongkos kerjanya. Urutannya
            // sengaja begitu: bahan dulu, jasa belakangan — persis urutan orang
            // memikirkannya saat menghitung sebuah knalpot.
            Section::make('Biaya Lain-lain')
                ->description('Ongkos kerja di luar bahan: chrome, poles, las, bending. Pilih jasanya dari master Jasa Produksi, lalu sebut berapa banyak dipakai.')
                ->columnSpanFull()
                ->schema([
                    Repeater::make('services')
                        ->relationship()
                        ->hiddenLabel()
                        ->addActionLabel('Tambah jasa')
                        ->orderColumn('sort_order')
                        ->table([
                            TableColumn::make('Jasa')->width('30%')->markAsRequired(),
                            TableColumn::make('Tarif')->width('18%')->alignment(Alignment::End),
                            TableColumn::make('Jumlah')->width('12%')->alignment(Alignment::End),
                            TableColumn::make('Biaya')->width('18%')->alignment(Alignment::End),
                            TableColumn::make('Catatan')->width('22%'),
                        ])
                        ->schema([
                            Select::make('production_service_id')
                                ->hiddenLabel()
                                ->options(fn () => ProductionService::query()
                                    ->active()->orderBy('sort_order')->orderBy('name')->pluck('name', 'id'))
                                ->searchable()->required()->distinct()
                                ->selectablePlaceholder(false)
                                ->placeholder('Pilih jasa')
                                ->live(),

                            Placeholder::make('tarif')
                                ->hiddenLabel()
                                ->content(fn (callable $get) => self::jasa($get)?->displayRate() ?? '—')
                                ->color(fn (callable $get) => self::jasa($get) ? null : 'gray'),

                            TextInput::make('qty')
                                ->hiddenLabel()
                                ->numeric()->minValue(0)->default(1)->required()->live(onBlur: true),

                            Placeholder::make('biaya')
                                ->hiddenLabel()
                                ->weight(FontWeight::Medium)
                                ->content(fn (callable $get) => self::biayaJasa($get))
                                ->color(fn (callable $get) => self::jasa($get) ? null : 'gray'),

                            TextInput::make('notes')
                                ->hiddenLabel()->maxLength(255)
                                ->placeholder('—'),
                        ]),

                    Placeholder::make('modal')
                        ->hiddenLabel()
                        ->weight(FontWeight::Medium)
                        ->content(fn (callable $get) => self::modalTotal($get)),
                ]),

            Section::make('Catatan')
                ->description('Hal yang perlu diingat saat mengerjakan resep ini.')
                ->columnSpanFull()
                ->collapsed()
                ->schema([Textarea::make('notes')->hiddenLabel()->rows(3)]),
        ]);
    }

    // ------------------------------------------------------------- pembantu

    /**
     * Seluruh komponen, diambil sekali lalu dipakai ulang.
     *
     * Penanda bagian memeriksa tetangga tiap barisnya, dan dua puluhan baris
     * yang saling memeriksa akan jadi ratusan query kalau dibiarkan.
     *
     * @return Collection<int, ExhaustComponent>
     */
    private static function semuaKomponen(): Collection
    {
        static $semua = null;

        return $semua ??= ExhaustComponent::with('parent')->get()->keyBy('id');
    }

    /**
     * Nama bagian, hanya di baris tempat bagiannya berganti.
     *
     * Header dan Silincer memakai komponen yang berbeda tapi terbaca menyambung
     * dalam satu tabel; tanda ini yang memisahkannya. Baris lain mengembalikan
     * string kosong supaya badge-nya tidak ikut muncul.
     */
    private static function penandaBagian(callable $get): string
    {
        $komponen = self::semuaKomponen()->get($get('exhaust_component_id'));

        if (! $komponen) {
            return '';
        }

        $nama = $komponen->parent?->name ?? '';
        $baris = $get('../../lines');

        // Tanpa isi repeaternya, tetangga tidak bisa diperiksa — jatuhkan ke
        // urutan master: komponen pertama sebuah bagian yang menyandang namanya.
        if (! is_array($baris)) {
            $pertama = self::semuaKomponen()
                ->where('parent_id', $komponen->parent_id)
                ->sortBy('sort_order')->first();

            return $pertama?->id === $komponen->id ? $nama : '';
        }

        // Satu komponen hanya boleh sekali dalam satu formula (`distinct`),
        // jadi id-nya cukup untuk menemukan baris ini di antara yang lain.
        $urutan = collect($baris)->pluck('exhaust_component_id')->values();
        $posisi = $urutan->search(fn ($id) => (int) $id === $komponen->id);

        if ($posisi === false) {
            return '';
        }

        $bagian = $urutan
            ->map(fn ($id) => self::semuaKomponen()->get($id)?->parent_id)
            ->all();

        return self::awalBagian($bagian)[$posisi] ? $nama : '';
    }

    /**
     * Baris mana saja yang memulai bagian baru, dari urutan bagian tiap baris.
     *
     * Dibandingkan dengan baris sebelumnya, bukan dengan daftar bagian yang
     * sudah lewat: kalau komponen Header diselipkan di tengah Silincer, baris
     * itu memang berganti bagian dan memang perlu ditandai.
     *
     * @param  array<int, int|string|null>  $bagianTiapBaris
     * @return array<int, bool>
     */
    public static function awalBagian(array $bagianTiapBaris): array
    {
        $awal = [];

        // Sengaja bukan null — null adalah nilai bagian yang sah (komponen
        // lepas), dan baris pertama harus tetap terhitung sebagai awal.
        $sebelumnya = false;

        foreach (array_values($bagianTiapBaris) as $i => $bagian) {
            $awal[$i] = $sebelumnya !== $bagian;
            $sebelumnya = $bagian;
        }

        return $awal;
    }

    /**
     * Dikelompokkan per bagian — "P1" ada di Header, "Sarangan" di Silincer,
     * dan daftar sepanjang dua puluhan baris jauh lebih cepat ditelusuri kalau
     * pengelompokannya kelihatan.
     *
     * @return array<string, array<int, string>>
     */
    private static function pilihanKomponen(): array
    {
        return ExhaustComponent::query()
            ->komponen()->where('is_active', true)->with('parent')
            ->get()
            ->sortBy(fn (ExhaustComponent $k) => [$k->parent?->sort_order ?? 0, $k->sort_order])
            ->groupBy(fn (ExhaustComponent $k) => $k->parent?->name ?? 'Lainnya')
            ->map(fn ($grup) => $grup->pluck('name', 'id')->all())
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

    /**
     * Baris yang sedang diketik, dipakai untuk menghitung tanpa menyimpan.
     *
     * Kolom ukuran di form menyimpan angka dalam satuan yang sedang dipilih,
     * bukan milimeter — konversinya harus dilakukan di sini, sekali.
     */
    private static function barisDari(callable $get): FormulaLine
    {
        return new FormulaLine([
            'input_mode' => $get('input_mode') ?: 'count',
            'size_unit' => $get('size_unit') ?: 'cm',
            'piece_length_mm' => self::keMm($get('piece_length_mm'), $get('size_unit')) ?: 0,
            'piece_width_mm' => self::keMm($get('piece_width_mm'), $get('size_unit')) ?: 0,
            'piece_count' => $get('piece_count') ?: 0,
        ]);
    }

    /**
     * Kebutuhan berikut biayanya: "400 mm · Rp40.000", "3 pcs · Rp7.500".
     *
     * Satu kolom, bukan dua: keduanya dibaca berpasangan, dan memecahnya
     * membuat dua kolom yang sama-sama terlalu sempit di layar 1366.
     */
    private static function kebutuhanBaris(callable $get): string
    {
        $barang = ProductionItem::find($get('production_item_id'));

        // Bahan yang masih kosong sudah terbaca sendiri di baris yang sama,
        // dan jumlahnya disebut di ringkasan bawah.
        if (! $barang) {
            return '—';
        }

        $qty = self::barisDari($get)->computeQty();

        return $barang->formatBase($qty)
            .' · Rp'.number_format($qty * $barang->basePrice(), 0, ',', '.');
    }

    /** Masih ada komponen yang bahannya belum ditentukan. */
    private static function adaYangKosong(callable $get): bool
    {
        $baris = $get('lines');

        if (! is_array($baris)) {
            return true;
        }

        return collect($baris)->contains(fn (array $isi) => blank($isi['production_item_id'] ?? null));
    }

    /**
     * Modal seluruh resep, dihitung dari isian yang sedang diketik.
     *
     * Ditaruh di bawah tabel karena inilah angka yang dicari: menjumlah dua
     * puluhan baris di kepala sambil mengisi adalah cara yang paling mudah
     * keliru, dan keliru di sini berakhir di harga jual.
     */
    private static function ringkasan(callable $get): string
    {
        $baris = $get('lines');

        if (! is_array($baris) || $baris === []) {
            return 'Belum ada komponen.';
        }

        $total = self::totalBahan($get);
        $belum = collect($baris)->filter(fn (array $isi) => blank($isi['production_item_id'] ?? null))->count();

        $hasil = self::hasil($get);
        $teks = 'Modal bahan Rp'.number_format($total / $hasil, 0, ',', '.').' per '.($get('output_unit') ?: 'set');

        if ($hasil != 1.0) {
            $teks .= ' (Rp'.number_format($total, 0, ',', '.').' sekali resep)';
        }

        return $teks.' · '.($belum > 0
            ? $belum.' dari '.count($baris).' komponen belum ada bahan'
            : 'seluruh komponen sudah ada bahan');
    }

    /** Biaya seluruh bahan untuk satu kali resep, dari isian yang sedang diketik. */
    private static function totalBahan(callable $get): float
    {
        $baris = $get('lines');

        if (! is_array($baris)) {
            return 0.0;
        }

        $barang = ProductionItem::findMany(
            collect($baris)->pluck('production_item_id')->filter()->unique()
        )->keyBy('id');

        $total = 0.0;

        foreach ($baris as $isi) {
            $item = $barang->get($isi['production_item_id'] ?? null);

            if (! $item) {
                continue;
            }

            $ambil = fn (string $kunci) => $isi[$kunci] ?? null;
            $total += self::barisDari($ambil)->computeQty() * $item->basePrice();
        }

        return $total;
    }

    /** Jasa yang sedang dipilih di satu baris biaya lain-lain. */
    private static function jasa(callable $get): ?ProductionService
    {
        return ProductionService::find($get('production_service_id'));
    }

    /** Biaya baris jasa ini: banyaknya dikali tarif masternya. */
    private static function biayaJasa(callable $get): string
    {
        $jasa = self::jasa($get);

        if (! $jasa) {
            return '—';
        }

        return 'Rp'.number_format($jasa->costFor((float) ($get('qty') ?: 0)), 0, ',', '.');
    }

    /** Biaya seluruh jasa untuk satu kali resep. */
    private static function totalJasa(callable $get): float
    {
        $baris = $get('services');

        if (! is_array($baris) || $baris === []) {
            return 0.0;
        }

        $jasa = ProductionService::findMany(
            collect($baris)->pluck('production_service_id')->filter()->unique()
        )->keyBy('id');

        return collect($baris)->sum(function (array $isi) use ($jasa) {
            $ini = $jasa->get($isi['production_service_id'] ?? null);

            return $ini?->costFor((float) ($isi['qty'] ?? 0)) ?? 0.0;
        });
    }

    /**
     * Modal sesungguhnya: bahan ditambah jasa.
     *
     * Angka inilah yang dipakai menetapkan harga jual, jadi ketiganya disebut
     * sekaligus — yang tampil cuma totalnya akan membuat orang lupa dari mana
     * kenaikannya datang saat suatu hari terasa mahal.
     */
    private static function modalTotal(callable $get): string
    {
        $hasil = self::hasil($get);
        $bahan = self::totalBahan($get) / $hasil;
        $jasa = self::totalJasa($get) / $hasil;
        $satuan = $get('output_unit') ?: 'set';

        $rp = fn (float $n) => 'Rp'.number_format($n, 0, ',', '.');

        return 'Modal total '.$rp($bahan + $jasa).' per '.$satuan
            .'  ·  bahan '.$rp($bahan).' + jasa '.$rp($jasa);
    }

    /** Sekali resep menghasilkan berapa unit; dijaga supaya tidak nol. */
    private static function hasil(callable $get): float
    {
        return max((float) ($get('output_qty') ?: 1), 0.001);
    }
}
