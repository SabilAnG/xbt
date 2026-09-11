<?php

namespace App\Filament\Resources\ProductionPurchases\Schemas;

use App\Models\ProductionItem;
use App\Models\ProductionPurchase;
use App\Models\Warehouse;
use App\Services\DocumentNumber;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\FontWeight;

/**
 * Nota pembelian bahan produksi.
 *
 * Diisi persis seperti nota tokonya: tujuh batang, sekian rupiah per batang.
 * Konversi ke satuan pakai tidak pernah diketik orang — ditampilkan saja
 * sebagai pemeriksaan, dan dihitung sungguhan saat dibukukan.
 */
class ProductionPurchaseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Nota Pembelian')
                ->description('Nomor dan tanggalnya mengikuti nota toko. Setelah dibukukan, isian ini dikunci.')
                ->columnSpanFull()
                ->columns(3)
                ->schema([
                    TextInput::make('invoice_number')
                        ->label('No. Nota')
                        ->required()->unique(ignoreRecord: true)
                        ->default(fn () => DocumentNumber::next('production_purchases'))
                        ->disabled(fn (?ProductionPurchase $record) => $record?->isPosted())
                        ->dehydrated(),

                    DatePicker::make('purchased_at')
                        ->label('Tanggal')
                        ->required()->default(now())
                        ->disabled(fn (?ProductionPurchase $record) => $record?->isPosted()),

                    TextInput::make('supplier_name')
                        ->label('Supplier')->maxLength(255)
                        ->placeholder('Toko Besi Jaya')
                        ->disabled(fn (?ProductionPurchase $record) => $record?->isPosted()),

                    // Bukan penentu, cuma isian awal tiap baris: satu nota bisa
                    // memuat pipa untuk gudang bahan mentah dan baut untuk
                    // gudang lain, dan gudang yang sesungguhnya ada di barisnya.
                    Select::make('warehouse_id')
                        ->label('Gudang Bawaan')
                        ->options(fn () => self::pilihanGudang())
                        ->searchable()->live()
                        ->default(fn () => Warehouse::where('type', 'bahan_mentah')->value('id'))
                        ->helperText('Mengisi otomatis tiap baris baru. Gudang tiap bahan masih bisa diubah sendiri-sendiri.')
                        ->disabled(fn (?ProductionPurchase $record) => $record?->isPosted()),

                    Select::make('wallet_id')
                        ->label('Dibayar dari dompet')
                        ->relationship('wallet', 'name')
                        ->searchable()->preload()
                        ->helperText('Kosongkan bila belum dibayar — kas tidak akan bergerak.')
                        ->disabled(fn (?ProductionPurchase $record) => $record?->isPosted()),

                    Placeholder::make('status')
                        ->label('Status')
                        ->content(fn (?ProductionPurchase $record) => $record?->displayStatus() ?? 'Draft')
                        ->helperText('Stok dan kas baru bergerak setelah dibukukan.'),
                ]),

            Section::make('Bahan yang Dibeli')
                ->description('Qty dan harga memakai satuan beli — batang, lembar, kg — seperti tertulis di nota toko. Tiap bahan menyebut sendiri masuk ke gudang mana.')
                ->columnSpanFull()
                ->schema([
                    Repeater::make('items')
                        ->relationship()
                        ->hiddenLabel()
                        ->addActionLabel('Tambah bahan')
                        ->disabled(fn (?ProductionPurchase $record) => $record?->isPosted())
                        ->table([
                            TableColumn::make('Bahan')->width('24%')->markAsRequired(),
                            TableColumn::make('Masuk Gudang')->width('18%')->markAsRequired(),
                            TableColumn::make('Qty')->width('10%')->alignment(Alignment::End),
                            TableColumn::make('Satuan')->width('9%'),
                            TableColumn::make('Harga Satuan')->width('14%')->alignment(Alignment::End),
                            TableColumn::make('Jadi Stok')->width('12%')->alignment(Alignment::End),
                            TableColumn::make('Subtotal')->width('13%')->alignment(Alignment::End),
                        ])
                        ->schema([
                            Select::make('production_item_id')
                                ->hiddenLabel()
                                ->options(fn () => ProductionItem::query()
                                    ->where('is_active', true)->orderBy('name')->pluck('name', 'id'))
                                ->searchable()->required()->distinct()
                                ->selectablePlaceholder(false)
                                ->placeholder('Pilih bahan')
                                ->live()
                                ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                    $bahan = ProductionItem::find($state);

                                    // Harga beli terakhir sebagai titik awal;
                                    // tinggal diperbaiki bila notanya beda.
                                    $set('unit_cost', (float) ($bahan?->cost_price ?? 0));

                                    // Gudang bawaan jenis barangnya kalau ada —
                                    // "Pipa selalu di gudang bahan mentah" sudah
                                    // dijawab sekali di master, tidak perlu
                                    // ditanyakan lagi tiap baris.
                                    if (blank($get('warehouse_id'))) {
                                        $set('warehouse_id',
                                            $bahan?->category?->default_warehouse_id
                                            ?? $get('../../warehouse_id'));
                                    }
                                }),

                            Select::make('warehouse_id')
                                ->hiddenLabel()
                                ->options(fn () => self::pilihanGudang())
                                ->searchable()->required()
                                ->selectablePlaceholder(false)
                                ->placeholder('Pilih gudang')
                                ->default(fn (callable $get) => $get('../../warehouse_id')),

                            TextInput::make('qty')
                                ->hiddenLabel()
                                ->numeric()->required()->default(1)->minValue(0.001)
                                ->live(onBlur: true),

                            Placeholder::make('satuan')
                                ->hiddenLabel()
                                ->content(fn (callable $get) => self::bahan($get)?->unit ?? '—')
                                ->color(fn (callable $get) => self::bahan($get) ? null : 'gray'),

                            TextInput::make('unit_cost')
                                ->hiddenLabel()
                                ->numeric()->required()->default(0)->minValue(0)->prefix('Rp')
                                ->live(onBlur: true),

                            // Pemeriksaan, bukan isian: tujuh batang pipa 6 m
                            // menjadi 42.000 mm, dan angka itu yang masuk stok.
                            Placeholder::make('jadi_stok')
                                ->hiddenLabel()
                                ->content(fn (callable $get) => self::jadiStok($get))
                                ->color(fn (callable $get) => self::bahan($get) ? null : 'gray'),

                            Placeholder::make('subtotal')
                                ->hiddenLabel()
                                ->weight(FontWeight::Medium)
                                ->content(fn (callable $get) => self::rp(
                                    (float) ($get('qty') ?: 0) * (float) ($get('unit_cost') ?: 0)
                                )),
                        ]),
                ]),

            Section::make('Potongan & Ongkos')
                ->columnSpanFull()
                ->columns(3)
                ->schema([
                    TextInput::make('discount')
                        ->label('Diskon')
                        ->numeric()->default(0)->minValue(0)->prefix('Rp')->live(onBlur: true)
                        ->disabled(fn (?ProductionPurchase $record) => $record?->isPosted()),

                    TextInput::make('shipping_cost')
                        ->label('Ongkir')
                        ->numeric()->default(0)->minValue(0)->prefix('Rp')->live(onBlur: true)
                        ->disabled(fn (?ProductionPurchase $record) => $record?->isPosted()),

                    Placeholder::make('total')
                        ->label('Total Nota')
                        ->weight(FontWeight::Medium)
                        ->content(fn (callable $get) => self::total($get))
                        ->helperText('Sejumlah inilah yang keluar dari dompet saat dibukukan.'),
                ]),

            Section::make('Catatan')
                ->columnSpanFull()
                ->collapsed()
                ->schema([Textarea::make('notes')->hiddenLabel()->rows(3)]),
        ]);
    }

    // ------------------------------------------------------------- pembantu

    /** @return array<int, string> */
    private static function pilihanGudang(): array
    {
        return Warehouse::query()
            ->where('is_active', true)->orderBy('sort_order')->pluck('name', 'id')->all();
    }

    private static function bahan(callable $get): ?ProductionItem
    {
        return ProductionItem::find($get('production_item_id'));
    }

    /** Berapa satuan pakai yang masuk gudang dari qty satuan beli ini. */
    private static function jadiStok(callable $get): string
    {
        $bahan = self::bahan($get);

        if (! $bahan) {
            return '—';
        }

        return $bahan->formatBase($bahan->toBase((float) ($get('qty') ?: 0)));
    }

    private static function total(callable $get): string
    {
        $baris = $get('items');

        $subtotal = is_array($baris)
            ? collect($baris)->sum(fn (array $isi) => (float) ($isi['qty'] ?? 0) * (float) ($isi['unit_cost'] ?? 0))
            : 0.0;

        $total = $subtotal - (float) ($get('discount') ?: 0) + (float) ($get('shipping_cost') ?: 0);

        return self::rp($total).'  ·  barang '.self::rp($subtotal);
    }

    private static function rp(float $angka): string
    {
        return 'Rp'.number_format($angka, 0, ',', '.');
    }
}
