<?php

namespace App\Filament\Resources\ExhaustComponents\Pages;

use App\Filament\Resources\ExhaustComponents\ExhaustComponentResource;
use App\Models\ExhaustComponent;
use Filament\Actions\Action;
use Filament\Resources\Pages\Page;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Isi satu bagian knalpot.
 *
 * Dibuka dengan mengklik kartunya. Di sinilah komponen ditambah dan
 * dipasangkan ke bahan bakunya — pekerjaan yang selalu dilakukan per bagian,
 * bukan menyisir satu daftar panjang.
 */
class IsiBagian extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = ExhaustComponentResource::class;

    protected string $view = 'filament.resources.exhaust-components.isi-bagian';

    public ExhaustComponent $record;

    public function mount(ExhaustComponent $record): void
    {
        $this->record = $record;
    }

    public function getTitle(): string
    {
        return $this->record->name;
    }

    public function getSubheading(): ?string
    {
        $jumlah = $this->record->children()->count();

        if ($jumlah === 0) {
            return 'Bagian ini belum berisi komponen.';
        }

        $belum = $this->record->children()->whereNull('production_item_id')->count();

        return $belum > 0
            ? $jumlah.' komponen · '.$belum.' belum ada bahan bakunya'
            : $jumlah.' komponen · bahan bakunya sudah lengkap';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => ExhaustComponent::query()
                ->with('item')
                ->where('parent_id', $this->record->id))
            ->defaultSort('sort_order')
            ->paginated(false)
            ->columns([
                TextColumn::make('name')
                    ->label('Komponen')->searchable()->sortable()->weight('medium')
                    ->description(fn (ExhaustComponent $r) => $r->code),

                TextColumn::make('bahan')
                    ->label('Bahan Baku')
                    ->getStateUsing(fn (ExhaustComponent $r) => $r->displayMaterial())
                    ->color(fn (ExhaustComponent $r) => $r->item === null ? 'danger' : null)
                    ->weight(fn (ExhaustComponent $r) => $r->item === null ? 'medium' : null)
                    ->description(fn (ExhaustComponent $r) => $r->item?->conversionLabel()),

                TextColumn::make('item.stock')
                    ->label('Stok bahan')->alignRight()->toggleable()
                    ->getStateUsing(fn (ExhaustComponent $r) => $r->item?->displayStock() ?? '—'),

                TextColumn::make('sort_order')
                    ->label('Urutan')->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('is_active')->label('Aktif')->boolean()->toggleable(),
            ])
            ->filters([
                Filter::make('tanpa_bahan')
                    ->label('Bahan bakunya belum dipilih')
                    ->query(fn (Builder $query) => $query->whereNull('production_item_id')),
            ])
            ->recordUrl(fn (ExhaustComponent $record) => ExhaustComponentResource::getUrl('edit', ['record' => $record]))
            ->emptyStateHeading('Belum ada komponen di bagian ini')
            ->emptyStateDescription('Tambahkan komponennya, lalu pasangkan bahan bakunya.')
            ->emptyStateIcon('heroicon-o-puzzle-piece');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('tambah')
                ->label('Tambah Komponen')
                ->icon('heroicon-o-plus')
                ->color('primary')
                // Bagiannya sudah pasti; jangan minta orang memilihnya lagi.
                ->url(fn () => ExhaustComponentResource::getUrl('create', ['bagian' => $this->record->id])),

            Action::make('kembali')
                ->label('Semua Bagian')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('gray')
                ->url(fn () => ExhaustComponentResource::getUrl('index')),
        ];
    }
}
