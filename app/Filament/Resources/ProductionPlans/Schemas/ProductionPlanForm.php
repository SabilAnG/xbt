<?php

namespace App\Filament\Resources\ProductionPlans\Schemas;

use App\Models\Formula;
use App\Models\ProductionPlan;
use App\Services\DocumentNumber;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProductionPlanForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Rencana')
                ->columns(3)
                ->schema([
                    TextInput::make('plan_number')
                        ->label('No. Rencana')
                        ->required()->unique(ignoreRecord: true)
                        ->default(fn () => DocumentNumber::next('production_plans')),

                    DatePicker::make('planned_for')
                        ->label('Untuk Periode')->required()->default(now()),

                    TextInput::make('title')
                        ->label('Judul')->maxLength(255)
                        ->placeholder('mis. Produksi Oktober')
                        ->helperText('Opsional, untuk memudahkan mencari.'),
                ]),

            Section::make('Mau Bikin Apa Saja')
                ->description('Isi target per formula. Sistem menjumlahkan kebutuhan bahannya, mengurangi dengan stok, lalu menyusun daftar belanjanya.')
                ->schema([
                    Repeater::make('lines')
                        ->relationship()
                        ->hiddenLabel()
                        ->addActionLabel('Tambah formula')
                        ->orderColumn('sort_order')
                        ->columns(12)
                        ->itemLabel(fn (array $state) => self::itemLabel($state))
                        ->schema([
                            Select::make('formula_id')
                                ->label('Formula')
                                ->options(fn () => Formula::where('is_active', true)
                                    ->orderBy('name')->pluck('name', 'id'))
                                ->searchable()->required()
                                ->distinct()->fixIndistinctState()
                                ->columnSpan(5)
                                ->live(),

                            TextInput::make('target_qty')
                                ->label('Target')
                                ->numeric()->required()->default(1)->minValue(0.001)
                                ->columnSpan(2)
                                ->live(onBlur: true)
                                ->helperText(fn (callable $get) => 'satuan: '
                                    .(Formula::find($get('formula_id'))?->output_unit ?? 'unit')),

                            Placeholder::make('modal')
                                ->label('Modal & waktu')
                                ->columnSpan(5)
                                ->content(fn (callable $get) => self::preview($get)),

                            TextInput::make('notes')->label('Catatan')
                                ->columnSpan(12)->maxLength(255),
                        ]),
                ]),

            Section::make('Nota Pembelian')
                ->visible(fn (?ProductionPlan $record) => $record?->isShopped())
                ->schema([
                    Placeholder::make('nota')
                        ->hiddenLabel()
                        ->content(fn (?ProductionPlan $record) => $record?->materialPurchase
                            ? 'Daftar belanja rencana ini sudah dibuatkan nota '
                                .$record->materialPurchase->invoice_number
                                .' senilai Rp '.number_format((float) $record->materialPurchase->total, 0, ',', '.')
                                .' (status '.($record->materialPurchase->isPosted() ? 'dibukukan' : 'draft').').'
                            : '-'),
                ]),

            Section::make('Catatan')
                ->collapsed()
                ->schema([Textarea::make('notes')->hiddenLabel()->rows(3)]),
        ]);
    }

    private static function itemLabel(array $state): string
    {
        $f = Formula::find($state['formula_id'] ?? null);

        if (! $f) {
            return 'Baris baru';
        }

        $qty = rtrim(rtrim(number_format((float) ($state['target_qty'] ?? 0), 2, ',', '.'), '0'), ',');

        return $f->name.' — '.$qty.' '.$f->output_unit;
    }

    private static function preview(callable $get): string
    {
        $f = Formula::with(['materials.material', 'costs.component', 'machines.machine'])
            ->find($get('formula_id'));

        if (! $f) {
            return 'Pilih formula dulu.';
        }

        $target = (float) ($get('target_qty') ?: 0);
        $batch = ceil($target / max((float) $f->output_qty, 0.001));
        $menit = $f->totalMinutes() * $batch;
        $jam = floor($menit / 60);

        return sprintf(
            'HPP %s/unit · modal %s · %dx resep · %s',
            'Rp '.number_format($f->hppPerUnit(), 0, ',', '.'),
            'Rp '.number_format($f->hppPerUnit() * $target, 0, ',', '.'),
            $batch,
            $jam > 0 ? sprintf('%d jam %d menit kerja', $jam, $menit - $jam * 60) : $menit.' menit kerja'
        );
    }
}
